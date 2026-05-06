"""
Pipeline principal de procesamiento de comprobantes electrónicos.

Orquesta todos los pasos del flujo:
  1. Validar y transformar el documento (Pydantic ya validó la entrada)
  2. Generar filename único
  3. Generar XML unsigned (xml_builder)
  4. Guardar XML unsigned en storage
  5. Decidir firma: local (signer) o PSE externo
  6. Guardar XML signed en storage
  7. Calcular hash del XML signed
  8. Generar QR
  9. Enviar a SUNAT / OSE vía sender
 10. Parsear CDR y mapear estado
 11. (Opcional) Generar PDF
 12. Retornar PipelineResponse con todos los artefactos

Equivalente a Facturalo.php en CoreFacturalo (método save + pipeline).
"""
from __future__ import annotations

import base64
import hashlib
import logging
from decimal import Decimal
from pathlib import Path
from typing import Any, Dict, Optional

from app.schemas.document import DocumentRequest, PipelineResponse
from app.services.document import build_xml, XmlSigner, compute_digest, generate_qr
from app.services.sender import (
    build_sunat_sender,
    build_pse_sender,
    build_ose_sender,
    SendResult,
)
from app.services.storage import StorageService
from app.services.store.document import (
    get_document_by_filename,
    create_document,
    update_document_state,
)
from app.core.config import settings

try:
    from sqlalchemy.orm import Session as _Session
except ImportError:  # por si acaso el env no tiene SQLAlchemy
    _Session = None  # type: ignore

logger = logging.getLogger(__name__)

# Mapeo de códigos SUNAT → estado interno
# Equivalente a Facturalo::validationCodeResponse() en PHP
_CODE_TO_STATE: Dict[str, tuple[str, str]] = {
    "0":    ("05", "Aceptado"),
    "-1":   ("09", "Rechazado - error de envío"),
}
# Códigos de observación (comprobantes con observaciones aún aceptados)
_OBSERVED_CODES_RANGE = range(2000, 3999)  # 2xxx = observaciones SUNAT

# Estado inicial al crear el comprobante
_STATE_REGISTERED = "01"
_STATE_SENT = "03"
_STATE_ACCEPTED = "05"
_STATE_OBSERVED = "07"
_STATE_REJECTED = "09"


async def run_document_pipeline(
    doc: DocumentRequest,
    company_config: dict,
    db: Optional["_Session"] = None,
) -> PipelineResponse:
    """
    Ejecuta el pipeline completo para un comprobante electrónico.

    Args:
        doc:            Datos del documento (ya validados por Pydantic).
        company_config: Configuración de la compañía obtenida del company_store.
        db:             Sesión SQLAlchemy. Si se provee y actions.persist=True,
                        el documento se persiste en BD con actualizaciones progresivas
                        de estado a lo largo del pipeline.

    Returns:
        PipelineResponse con todos los artefactos y el estado final.
    """
    storage = StorageService(company_config["legal_number"])
    errors: list[str] = []
    state_type_id = _STATE_REGISTERED
    state_description = "Registrado"
    sunat_code = None
    sunat_description = None
    sunat_notes: list[str] = []
    xml_unsigned_b64: Optional[str] = None
    xml_signed_b64: Optional[str] = None
    cdr_b64: Optional[str] = None
    hash_value: Optional[str] = None
    qr_b64: Optional[str] = None
    document_id: Optional[int] = None  # ID en BD si persist=True

    # ------------------------------------------------------------------
    # 1. Generar nombre de archivo (equivalente a Functions::filename)
    # ------------------------------------------------------------------
    filename = _build_filename(doc, company_config)
    logger.info("Iniciando pipeline", extra={"doc_filename": filename, "doc_type": doc.type.value})

    # ------------------------------------------------------------------
    # 1b. Persistencia: idempotencia y registro inicial
    # ------------------------------------------------------------------
    should_persist = bool(db and doc.actions.persist)
    if should_persist:
        company_id = company_config.get("id")
        existing = get_document_by_filename(db, company_id, filename)
        if existing is not None:
            # Documento ya existe: si está en estado terminal, retornar el guardado
            _TERMINAL = {"05", "07", "09"}
            if existing.state_type_id in _TERMINAL:
                logger.info(
                    "Idempotencia: documento %s ya procesado (state=%s)",
                    filename, existing.state_type_id,
                )
                return _build_response(
                    success=(existing.state_type_id in ("05", "07")),
                    filename=filename,
                    state_type_id=existing.state_type_id,
                    state_description=_state_label(existing.state_type_id),
                    sunat_code=existing.sunat_code,
                    sunat_description=existing.sunat_description,
                    sunat_notes=existing.sunat_notes or [],
                    hash=existing.hash,
                    document_id=existing.id,
                )
            # Está en proceso: incrementar intento y continuar
            update_document_state(
                db, existing.id, existing.state_type_id, increment_attempt=True
            )
            document_id = existing.id
        else:
            # Nuevo documento: registrar en BD (estado 01)
            new_doc = create_document(db, company_id, doc, filename)
            document_id = new_doc.id
    # ------------------------------------------------------------------
    # 2. Construir contexto para la plantilla Jinja2
    # ------------------------------------------------------------------
    context = _build_template_context(doc, company_config)

    # ------------------------------------------------------------------
    # 3. Generar XML unsigned
    # ------------------------------------------------------------------
    try:
        xml_unsigned_str = build_xml(doc.type.value, context)
        xml_unsigned_bytes = xml_unsigned_str.encode("utf-8")
        xml_unsigned_b64 = base64.b64encode(xml_unsigned_bytes).decode("ascii")
        storage.save("unsigned", filename + ".xml", xml_unsigned_bytes)
        logger.debug("XML unsigned generado (%d bytes)", len(xml_unsigned_bytes))  # noqa
    except Exception as exc:
        logger.exception("Error generando XML unsigned")
        errors.append(f"xml_builder: {exc}")
        return _build_response(
            success=False,
            filename=filename,
            state_type_id=_STATE_REJECTED,
            state_description="Error generando XML",
            errors=errors,
            document_id=document_id,
        )

    # ------------------------------------------------------------------
    # 4. Firma XML (local o PSE externo)
    # ------------------------------------------------------------------
    xml_signed_bytes: Optional[bytes] = None

    if doc.xml_signed_b64:
        # El consumidor ya envió el XML firmado por PSE
        xml_signed_bytes = base64.b64decode(doc.xml_signed_b64)
        logger.info("Usando XML firmado externo (PSE)")
    elif company_config.get("send_document_to_pse") and doc.actions.send_xml_signed:
        # Delegar firma al proveedor PSE
        xml_signed_bytes = await _send_to_pse(xml_unsigned_bytes, filename, company_config)
    else:
        # Firma local con python-xmlsec
        xml_signed_bytes = _sign_locally(xml_unsigned_bytes, company_config, errors)

    if xml_signed_bytes is None:
        return _build_response(
            success=False,
            filename=filename,
            state_type_id=_STATE_REJECTED,
            state_description="Error en firma XML",
            xml_unsigned_b64=xml_unsigned_b64,
            errors=errors,
            document_id=document_id,
        )

    xml_signed_b64 = base64.b64encode(xml_signed_bytes).decode("ascii")
    storage.save("signed", filename + ".xml", xml_signed_bytes)

    # Rutas para persistencia
    unsigned_path = str(storage.get_path("unsigned", filename + ".xml"))
    signed_path = str(storage.get_path("signed", filename + ".xml"))

    # Actualizar paths en BD
    if should_persist and document_id:
        update_document_state(
            db,
            document_id,
            _STATE_REGISTERED,
            xml_unsigned_path=unsigned_path,
            xml_signed_path=signed_path,
        )

    # ------------------------------------------------------------------
    # 5. Calcular hash del XML firmado (equivalente a XmlHash::getHash)
    # ------------------------------------------------------------------
    hash_value = compute_digest(xml_signed_bytes, algorithm="sha256")

    # ------------------------------------------------------------------
    # 6. Generar QR
    # ------------------------------------------------------------------
    try:
        qr_b64 = generate_qr(_build_qr_text(doc, company_config, hash_value))
    except Exception as exc:
        logger.warning("No se pudo generar el QR: %s", exc)

    # Actualizar hash en BD
    if should_persist and document_id:
        update_document_state(
            db,
            document_id,
            _STATE_REGISTERED,
            hash_value=hash_value,
        )

    # ------------------------------------------------------------------
    # 7. Enviar a SUNAT / OSE
    # ------------------------------------------------------------------
    if not doc.actions.send_xml_signed:
        state_type_id = _STATE_REGISTERED
        state_description = "Generado (sin envío a SUNAT)"
        # Actualizar estado en BD con processed_at
        if should_persist and document_id:
            update_document_state(
                db,
                document_id,
                state_type_id,
                mark_processed=True,
            )
        return _build_response(
            success=True,
            filename=filename,
            state_type_id=state_type_id,
            state_description=state_description,
            xml_unsigned_b64=xml_unsigned_b64,
            xml_signed_b64=xml_signed_b64,
            hash=hash_value,
            qr_b64=qr_b64,
            document_id=document_id,
        )

    state_type_id = _STATE_SENT
    state_description = "Enviado a SUNAT"

    # Actualizar estado a "03" antes de enviar
    if should_persist and document_id:
        update_document_state(db, document_id, _STATE_SENT)

    # Tipo de comprobante de resumen (envío asíncrono con ticket)
    _SUMMARY_TYPES = {"summary", "voided"}
    is_summary = doc.type.value in _SUMMARY_TYPES
    soap_send_type = company_config.get("soap_send_type", "sunat")

    try:
        if soap_send_type == "pse" and doc.actions.send_xml_signed:
            # -------------------------------------------------------
            # Flujo PSE: el proveedor firma el XML unsigned →
            # devuelve XML firmado + hash; luego se envía a SUNAT.
            # -------------------------------------------------------
            pse = build_pse_sender(company_config)
            pse_result: SendResult = pse.sign_xml(filename, xml_unsigned_bytes)
            if not pse_result.success or not pse_result.xml_signed_b64:
                raise RuntimeError(
                    f"PSE firma fallida: [{pse_result.code}] {pse_result.description}"
                )
            xml_signed_bytes = base64.b64decode(pse_result.xml_signed_b64)
            xml_signed_b64 = pse_result.xml_signed_b64
            if pse_result.hash_code:
                hash_value = pse_result.hash_code  # usar hash entregado por PSE
            storage.save("signed", filename + ".xml", xml_signed_bytes)

            sunat = build_sunat_sender(doc.type.value, company_config)
            send_result: SendResult = (
                sunat.send_summary(filename, xml_signed_bytes)
                if is_summary
                else sunat.send_bill(filename, xml_signed_bytes)
            )

        elif soap_send_type == "ose":
            # -------------------------------------------------------
            # Flujo OSE: enviar XML firmado al OSE;
            # el OSE lo remite a SUNAT y devuelve el CDR.
            # -------------------------------------------------------
            ose = build_ose_sender(company_config)
            send_result = (
                ose.send_summary(filename, xml_signed_bytes)
                if is_summary
                else ose.send_bill(filename, xml_signed_bytes)
            )

        else:
            # -------------------------------------------------------
            # Flujo SUNAT directo (predeterminado)
            # -------------------------------------------------------
            sunat = build_sunat_sender(doc.type.value, company_config)
            send_result = (
                sunat.send_summary(filename, xml_signed_bytes)
                if is_summary
                else sunat.send_bill(filename, xml_signed_bytes)
            )

        # Procesar SendResult (dataclass)
        if is_summary and send_result.ticket:
            # Resúmenes quedan en estado "enviado" hasta consultar el ticket
            sunat_code = send_result.ticket
            sunat_description = send_result.description
            state_type_id = _STATE_SENT
            state_description = f"Resumen enviado. Ticket: {sunat_code}"
            # Guardar el ticket en BD para poder resolver el estado después
            if should_persist and document_id:
                update_document_state(
                    db, document_id, _STATE_SENT, ticket=sunat_code
                )
        else:
            sunat_code = send_result.code
            sunat_description = send_result.description
            sunat_notes = send_result.notes
            if send_result.cdr_b64:
                cdr_b64 = send_result.cdr_b64
                # Guardar bajo la misma carpeta {filename}/cdr/{filename}.zip
                storage.save("cdr", filename + ".zip", base64.b64decode(cdr_b64))
            state_type_id, state_description = _resolve_state(sunat_code)
            # Actualizar estado final en BD
            if should_persist and document_id:
                cdr_local_path = str(storage.get_path("cdr", filename + ".zip")) if cdr_b64 else None
                update_document_state(
                    db,
                    document_id,
                    state_type_id,
                    sunat_code=sunat_code,
                    sunat_description=sunat_description,
                    sunat_notes=sunat_notes if sunat_notes else None,
                    cdr_path=cdr_local_path,
                    mark_processed=True,
                )

    except Exception as exc:
        logger.exception("Error enviando a SUNAT", extra={"doc_filename": filename})
        errors.append(f"sender: {exc}")
        state_type_id = _STATE_REJECTED
        state_description = "Error de envío"
        if should_persist and document_id:
            update_document_state(
                db, document_id, _STATE_REJECTED,
                sunat_description=str(exc),
                mark_processed=True,
            )

    return _build_response(
        success=(state_type_id in (_STATE_ACCEPTED, _STATE_OBSERVED)),
        filename=filename,
        state_type_id=state_type_id,
        state_description=state_description,
        sunat_code=sunat_code,
        sunat_description=sunat_description,
        sunat_notes=sunat_notes,
        xml_unsigned_b64=xml_unsigned_b64,
        xml_signed_b64=xml_signed_b64,
        cdr_b64=cdr_b64,
        hash=hash_value,
        qr_b64=qr_b64,
        errors=errors,
        document_id=document_id,
    )


# ---------------------------------------------------------------------------
# Helpers del pipeline
# ---------------------------------------------------------------------------

def _build_filename(doc: DocumentRequest, company_config: dict) -> str:
    """
    Genera el nombre de archivo del comprobante.

    Formato SUNAT: {RUC}-{tipo_doc_id}-{serie}-{numero}
    Equivalente a Functions::filename() en PHP.
    """
    if doc.filename:
        return doc.filename

    ruc = company_config["legal_number"]
    return f"{ruc}-{doc.document_type_id}-{doc.series}-{doc.number}"


def _build_template_context(doc: DocumentRequest, company_config: dict) -> Dict[str, Any]:
    """
    Construye el diccionario de contexto para la plantilla Jinja2.

    Mezcla datos del documento + datos de la compañía emisora.
    Aplana los sub-modelos anidados (totals, invoice, note) para que los templates
    los accedan con notación plana igual que en los templates PHP originales.
    """
    doc_dict = doc.model_dump(mode="python")

    # Aplanar totals → document.*  (ej. totals.total_igv → total_igv)
    if totals := doc_dict.pop("totals", None):
        doc_dict.update(totals)

    # Extraer operation_type_id y date_of_due del sub-modelo invoice
    if invoice_sub := doc_dict.get("invoice"):
        doc_dict.setdefault("operation_type_id", invoice_sub.get("operation_type_id", "0101"))
        doc_dict.setdefault("date_of_due", invoice_sub.get("date_of_due"))

    # Valores monetarios precalculados que el template puede referenciar
    doc_dict.setdefault("tot_charges", "0.00")
    doc_dict.setdefault("total_discount_no_base", "0.00")
    doc_dict.setdefault("fee_total", "0.00")
    doc_dict.setdefault("subtotal", doc_dict.get("total", "0.00"))

    return {
        # Emisor
        "company": {
            "legal_number": company_config["legal_number"],
            "legal_name": company_config.get("legal_name") or company_config.get("legal_name"),
            "trade_name": company_config.get("trade_name") or company_config.get("legal_name"),
            "signature_uri": company_config.get("signature_uri", "IDFirma"),
            "signature_note": company_config.get("signature_note", "Empresa emisora"),
        },
        # Documento
        "document": doc_dict,
        # Auxiliares de formato (los filtros Jinja2 se encargan del formato decimal)
        "ubl_version": "2.1",
        "customization_id": _get_customization_id(doc.document_type_id),
    }


def _get_customization_id(document_type_id: str) -> str:
    """
    Retorna el CustomizationID correspondiente al tipo de comprobante.

    Definido en las especificaciones FE SUNAT UBL 2.1.
    """
    mapping = {
        "01": "2.0",  # Factura
        "03": "2.0",  # Boleta
        "07": "2.0",  # Nota de crédito
        "08": "2.0",  # Nota de débito
        "20": "1.0",  # Retención
        "40": "1.0",  # Percepción
        "09": "2.0",  # Guía de remisión remitente
        "31": "2.0",  # Guía de remisión transportista
    }
    return mapping.get(document_type_id, "2.0")


def _sign_locally(
    xml_bytes: bytes,
    company_config: dict,
    errors: list[str],
) -> Optional[bytes]:
    """
    Firma el XML localmente usando python-xmlsec.

    Soporta certificados .p12 (PKCS12) y .pem (combinado clave+cert).
    Retorna el XML firmado o None si hay error.
    """
    try:
        signer = XmlSigner()
        cert_path = company_config.get("certificate_path")
        cert_password = company_config.get("certificate_password")
        cert_format = company_config.get("certificate_format", "")

        if not cert_path or not Path(cert_path).exists():
            raise FileNotFoundError(f"Certificado no encontrado en: {cert_path}")

        # Detectar formato: campo explícito en config o extensión del archivo
        if cert_format == "pem" or str(cert_path).endswith(".pem"):
            signer.load_combined_pem(cert_path)
        else:
            signer.load_p12(cert_path, cert_password)

        return signer.sign(xml_bytes)
    except Exception as exc:
        logger.exception("Error en firma local")
        errors.append(f"signer: {exc}")
        return None


async def _send_to_pse(
    xml_bytes: bytes,
    filename: str,
    company_config: dict,
) -> Optional[bytes]:
    """
    Envía el XML unsigned al proveedor PSE y recibe el XML firmado.

    Delegación directa a PseRestSender.sign_xml().
    Conservado como helper interno para compatibilidad con llamadas externas.
    """
    pse = build_pse_sender(company_config)
    result: SendResult = pse.sign_xml(filename, xml_bytes)
    if result.success and result.xml_signed_b64:
        return base64.b64decode(result.xml_signed_b64)
    logger.warning("PSE sign_xml falló: [%s] %s", result.code, result.description)
    return None


def _build_qr_text(doc: DocumentRequest, company_config: dict, hash_value: str) -> str:
    """
    Construye el texto del QR según el formato SUNAT.

    Formato: RUC|tipo|serie|numero|igv|total|fecha|tipo_doc_cliente|num_doc_cliente|hash
    Equivalente a Facturalo::getQr() en PHP.
    """
    customer_doc_type = ""
    customer_doc_number = ""
    if doc.customer:
        customer_doc_type = doc.customer.identity_document_type_id or ""
        customer_doc_number = doc.customer.number or ""

    total_igv = str(doc.totals.total_igv) if doc.totals else "0.00"
    total = str(doc.totals.total) if doc.totals else "0.00"

    parts = [
        company_config["legal_number"],
        doc.document_type_id,
        doc.series,
        doc.number,
        total_igv,
        total,
        doc.date_of_issue,
        customer_doc_type,
        customer_doc_number,
        hash_value,
    ]
    return "|".join(parts)


def _resolve_state(sunat_code: Optional[str]) -> tuple[str, str]:
    """
    Mapea el código de respuesta SUNAT a un estado interno.

    Equivalente a Facturalo::validationCodeResponse() en PHP.
    """
    if sunat_code is None:
        return _STATE_REJECTED, "Sin respuesta de SUNAT"

    code_int: Optional[int] = None
    try:
        code_int = int(sunat_code)
    except (ValueError, TypeError):
        pass

    if code_int == 0:
        return _STATE_ACCEPTED, "Aceptado"
    if code_int is not None and code_int in range(2000, 4000):
        return _STATE_OBSERVED, f"Aceptado con observaciones (código {sunat_code})"
    return _STATE_REJECTED, f"Rechazado por SUNAT (código {sunat_code})"


def _state_label(state_type_id: str) -> str:
    """Descripción legible de un estado para la response de idempotencia."""
    return {
        "01": "Registrado",
        "03": "Enviado a SUNAT",
        "05": "Aceptado",
        "07": "Aceptado con observaciones",
        "09": "Rechazado",
    }.get(state_type_id, state_type_id)


def _build_response(
    success: bool,
    filename: str,
    state_type_id: str,
    state_description: str,
    sunat_code: Optional[str] = None,
    sunat_description: Optional[str] = None,
    sunat_notes: Optional[list] = None,
    xml_unsigned_b64: Optional[str] = None,
    xml_signed_b64: Optional[str] = None,
    cdr_b64: Optional[str] = None,
    hash: Optional[str] = None,
    qr_b64: Optional[str] = None,
    pdf_b64: Optional[str] = None,
    errors: Optional[list] = None,
    document_id: Optional[int] = None,
) -> PipelineResponse:
    return PipelineResponse(
        success=success,
        filename=filename,
        state_type_id=state_type_id,
        state_description=state_description,
        sunat_code=sunat_code,
        sunat_description=sunat_description,
        sunat_notes=sunat_notes or [],
        xml_unsigned_b64=xml_unsigned_b64,
        xml_signed_b64=xml_signed_b64,
        cdr_b64=cdr_b64,
        hash=hash,
        qr_b64=qr_b64,
        pdf_b64=pdf_b64,
        errors=errors or [],
        document_id=document_id,
    )
