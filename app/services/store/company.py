"""
Lógica de persistencia de compañías.

Almacén principal: PostgreSQL vía SQLAlchemy.
Capa de caché:     Redis (TTL configurable; degradación silenciosa si no hay Redis).
Certificados:      Filesystem local (storage/RUC/certificate/).

Flujo de lectura:  Redis → PostgreSQL → None
Flujo de escritura: disco (cert) → PostgreSQL (upsert) → invalidar Redis
"""
import base64
import logging
import os
import secrets
from pathlib import Path
from typing import Optional

from sqlalchemy.orm import Session

from app.core.cache import cache_delete, cache_get, cache_set
from app.core.config import settings
from app.models.company_model import Company
from app.schemas.company import CompanyRegisterRequest
from app.services.document.signer import XmlSigner

logger = logging.getLogger(__name__)

_CACHE_KEY_PREFIX = "company:"


# ---------------------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------------------

def _company_cache_key(legal_number: str) -> str:
    """Construye la clave Redis para los datos de una compañía."""
    return f"{_CACHE_KEY_PREFIX}{legal_number}"


def _company_to_dict(company: Company) -> dict:
    """
    Convierte el modelo ORM Company a diccionario serializable.
    Usado para almacenar en Redis y construir la respuesta de la API.
    """
    return {
        "id":                    company.id,
        "legal_number":          company.legal_number,
        "legal_name":            company.legal_name,
        "trade_name":            company.trade_name,
        "sunat_mode":            company.sunat_mode,
        "soap_send_type":        company.soap_send_type,
        "soap_username":         company.soap_username,
        "soap_password":         company.soap_password,
        "certificate_password":  company.certificate_password,
        "certificate_format":    company.certificate_format,
        "certificate_path":      company.certificate_path,
        "pse_provider":          company.pse_provider,
        "pse_username":          company.pse_username,
        "pse_password":          company.pse_password,
        "pse_token_url":         company.pse_token_url,
        "pse_generate_url":      company.pse_generate_url,
        "pse_send_url":          company.pse_send_url,
        "pse_query_url":         company.pse_query_url,
        "send_document_to_pse":  company.send_document_to_pse,
        "response_mode":         company.response_mode,
        "wait_seconds":          company.wait_seconds,
        "auto_poll_ticket":      company.auto_poll_ticket,
        "ticket_wait_seconds":   company.ticket_wait_seconds,
        "ticket_poll_interval_ms": company.ticket_poll_interval_ms,
        "api_token":             company.api_token,
        "status":                company.status,
        "created_at":            company.created_at.isoformat() if company.created_at else None,
        "updated_at":            company.updated_at.isoformat() if company.updated_at else None,
    }


def _cert_path(legal_number: str, extension: str = ".p12") -> Path:
    """Retorna la ruta donde se guarda el certificado de la compañía."""
    path = Path(settings.STORAGE_LOCAL_PATH) / legal_number / "certificate"
    path.mkdir(parents=True, exist_ok=True)
    return path / f"certificate{extension}"


def _detect_cert_format(cert_bytes: bytes) -> tuple[str, str]:
    """
    Detecta el formato del certificado a partir de su contenido.
    Retorna (extension, format_name): ('.pem', 'pem') o ('.p12', 'p12').
    """
    if cert_bytes.lstrip()[:10].startswith(b"-----BEGIN"):
        return ".pem", "pem"
    return ".p12", "p12"


def _save_and_validate_certificate(
    legal_number: str,
    payload: CompanyRegisterRequest,
) -> tuple[str, str]:
    """
    Decodifica, valida y persiste el certificado en disco.

    - Detecta automáticamente el formato (PEM / P12).
    - Valida la contraseña contra el certificado antes de persistir.
    - Aplica permisos restrictivos (0o600) al archivo resultante.

    Retorna (cert_format, cert_path_str). Lanza ValueError si es inválido.
    """
    try:
        cert_bytes = base64.b64decode(payload.certificate_b64)
    except Exception as exc:
        raise ValueError(f"El certificado no es un base64 válido: {exc}") from exc

    cert_extension, cert_format = _detect_cert_format(cert_bytes)
    cert_file = _cert_path(legal_number, cert_extension)
    cert_file.write_bytes(cert_bytes)

    try:
        if cert_format == "p12":
            signer = XmlSigner()
            signer.load_p12(cert_file, payload.certificate_password)
        else:  # pem
            pem_text = cert_bytes.decode("utf-8", errors="ignore")
            if "ENCRYPTED" in pem_text or "Proc-Type: 4,ENCRYPTED" in pem_text:
                cert_file.unlink(missing_ok=True)
                raise ValueError(
                    "PEM encriptado detectado: sube un PEM sin passphrase o un .p12 con contraseña."
                )
            signer = XmlSigner()
            signer.load_combined_pem(cert_file)
    except ValueError:
        raise
    except Exception as exc:
        cert_file.unlink(missing_ok=True)
        raise ValueError(f"No se pudo validar el certificado: {exc}") from exc

    try:
        os.chmod(cert_file, 0o600)
    except NotImplementedError:
        pass  # Windows no soporta chmod completo; aceptable en dev

    return cert_format, str(cert_file)


# ---------------------------------------------------------------------------
# API pública del módulo
# ---------------------------------------------------------------------------

def store_company(db: Session, payload: CompanyRegisterRequest) -> dict:
    """
    Persiste la compañía en PostgreSQL (upsert) e invalida la caché Redis.

    Pasos:
        1. Validar y guardar el certificado en disco.
        2. Buscar compañía existente por legal_number.
        3. Crear o actualizar el registro en BD (upsert manual).
        4. Hacer commit y refrescar el objeto para obtener id/timestamps.
        5. Invalidar caché Redis → próxima lectura irá directo a BD.
        6. Retornar dict serializable con los datos persistidos.

    Lanza ValueError si el certificado es inválido.
    """
    legal_number = payload.legal_number
    cert_format, cert_path_str = _save_and_validate_certificate(legal_number, payload)

    existing: Optional[Company] = (
        db.query(Company).filter(Company.legal_number == legal_number).first()
    )

    # Campos a establecer tanto en creación como en actualización
    campos = {
        "legal_name":            payload.legal_name,
        "trade_name":            payload.trade_name,
        "sunat_mode":            payload.sunat_mode.value,
        "soap_send_type":        payload.soap_send_type.value,
        "soap_username":         payload.soap_username,
        "soap_password":         payload.soap_password,
        "certificate_password":  payload.certificate_password,
        "certificate_format":    cert_format,
        "certificate_path":      cert_path_str,
        "pse_provider":          payload.pse_provider.value if payload.pse_provider else None,
        "pse_username":          payload.pse_username,
        "pse_password":          payload.pse_password,
        "pse_token_url":         payload.pse_token_url,
        "pse_generate_url":      payload.pse_generate_url,
        "pse_send_url":          payload.pse_send_url,
        "pse_query_url":         payload.pse_query_url,
        "send_document_to_pse":  payload.send_document_to_pse,
        "response_mode":         payload.response_mode,
        "wait_seconds":          payload.wait_seconds,
        "auto_poll_ticket":      payload.auto_poll_ticket,
        "ticket_wait_seconds":   payload.ticket_wait_seconds,
        "ticket_poll_interval_ms": payload.ticket_poll_interval_ms,
        "api_token":             payload.api_token,
        "status":                payload.status,
    }

    if existing is None:
        # Compañía nueva: siempre generar token (el payload no puede definirlo)
        campos["api_token"] = secrets.token_urlsafe(32)
        company = Company(legal_number=legal_number, **campos)
        db.add(company)
        logger.info("Compañía nueva: %s", legal_number)
    else:
        # Actualización: el api_token del payload se ignora completamente.
        # - Si ya tiene token → conservarlo.
        # - Si no tiene token (registro legacy) → generar uno nuevo.
        campos["api_token"] = existing.api_token or secrets.token_urlsafe(32)
        for attr, value in campos.items():
            setattr(existing, attr, value)
        company = existing
        logger.info("Compañía actualizada: %s", legal_number)

    db.commit()
    db.refresh(company)

    # Invalidar caché Redis → próxima lectura obtiene datos frescos de BD
    cache_delete(_company_cache_key(legal_number))

    return _company_to_dict(company)


def get_company_by_token(db: Session, api_token: str) -> Optional[dict]:
    """
    Recupera la configuración de una compañía por su api_token.

    No usa caché Redis porque el token es un secreto; buscarlo por RUC
    (clave de caché) requeriría conocer el RUC de antemano.
    Una consulta directa a BD es suficiente dado el bajo volumen esperado.
    """
    company: Optional[Company] = (
        db.query(Company).filter(Company.api_token == api_token).first()
    )
    if company is None:
        return None
    return _company_to_dict(company)


def get_company(db: Session, legal_number: str) -> Optional[dict]:
    """
    Recupera la configuración de una compañía.

    Orden de consulta:
        1. Redis (caché con TTL de 5 min) → retorno rápido.
        2. PostgreSQL → guarda en Redis para las próximas lecturas.
        3. None si no existe en ninguna capa.
    """
    cache_key = _company_cache_key(legal_number)

    # 1. Intentar desde Redis
    cached = cache_get(cache_key)
    if cached is not None:
        logger.info("Cache hit Redis para compañía %s", legal_number)
        return cached

    # 2. Consultar PostgreSQL
    company: Optional[Company] = (
        db.query(Company).filter(Company.legal_number == legal_number).first()
    )
    if company is None:
        return None

    # 3. Poblar caché Redis para próximas lecturas
    data = _company_to_dict(company)
    cache_set(cache_key, data)
    return data
