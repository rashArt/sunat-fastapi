"""
Clientes SOAP/REST para envío de comprobantes a SUNAT, OSE y PSE.

Arquitectura multi-proveedor extensible:
  SunatSoapSender → Envío directo a SUNAT vía SOAP con WSSE UsernameToken
  PseRestSender   → PSE firma el XML sin firmar vía REST y retorna XML firmado
                    El envío a SUNAT se hace por separado con SunatSoapSender
  OseRestSender   → OSE recibe XML firmado, lo envía a SUNAT y retorna CDR

Para agregar un nuevo proveedor REST (PSE/OSE):
  - Opción A: agregar su entrada en el diccionario _PSE_PROVIDERS
  - Opción B: usar pse_provider='custom' con URLs explícitas en company_config

Equivale a los servicios en CoreFacturalo/WS/ y PseService/:
  BillSender         → SunatSoapSender.send_bill
  SummarySender      → SunatSoapSender.send_summary
  ExtService         → SunatSoapSender.get_status (ticket)
  ConsultCdrService  → SunatSoapSender.consult_cdr
  ServiceSendFact    → PseRestSender
  ServiceOseSendFact → OseRestSender
"""
from __future__ import annotations

import base64
import io
import logging
import re
import zipfile
from dataclasses import dataclass, field
from typing import Optional

logger = logging.getLogger(__name__)

try:
    from zeep import Client as ZeepClient, Settings as ZeepSettings
    from zeep.transports import Transport
    from zeep.wsse.username import UsernameToken
    _ZEEP_AVAILABLE = True
except ImportError:
    _ZEEP_AVAILABLE = False
    logger.warning("zeep no disponible. El envío SOAP a SUNAT no funcionará.")

try:
    import httpx
    _HTTPX_AVAILABLE = True
except ImportError:
    _HTTPX_AVAILABLE = False
    logger.warning("httpx no disponible. El envío REST a PSE/OSE no funcionará.")


# ---------------------------------------------------------------------------
# WSDLs locales bundleados (equivalente a CoreFacturalo/WS/Client/Resources/wsdl/)
# Igual que CoreFacturalo: carga los WSDLs desde disco en lugar de descargarlos
# de SUNAT en cada inicialización (evita 401 intermitente en beta).
# ---------------------------------------------------------------------------

_WSDL_DIR = str(__import__('pathlib').Path(__file__).parent.parent / 'wsdl')


def _local_wsdl(filename: str) -> str:
    """Retorna la ruta al archivo WSDL local."""
    import os
    return os.path.join(_WSDL_DIR, filename)


# Binding QNames extraídos del WSDL bundleado (los usa create_service para override de URL)
_BINDING_BILL = "{http://service.gem.factura.comppago.registro.servicio.sunat.gob.pe/}BillServicePortBinding"
_BINDING_CONSULT = "{http://service.ws.consulta.comppago.electronico.registro.servicio2.sunat.gob.pe/}BillConsultServicePortBinding"


# ---------------------------------------------------------------------------
# Endpoints SUNAT (SOAP / WSDL)
# ---------------------------------------------------------------------------

_SUNAT_WSDL: dict[str, dict[str, str]] = {
    # Facturación electrónica (facturas, boletas, NC, ND, resúmenes, bajas)
    "fe": {
        "beta": _local_wsdl("billService.wsdl"),
        "prod": _local_wsdl("billService.wsdl"),
        # URL del endpoint real para invocar operaciones
        "beta_url": "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService",
        "prod_url": "https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService",
        "binding": _BINDING_BILL,
    },
    # Retenciones y percepciones
    "re": {
        "beta": _local_wsdl("billService.wsdl"),
        "prod": _local_wsdl("billService.wsdl"),
        "beta_url": "https://e-beta.sunat.gob.pe/ol-ti-itemision-otroscpe-gem-beta/billService",
        "prod_url": "https://e-factura.sunat.gob.pe/ol-ti-itemision-otroscpe-gem/billService",
        "binding": _BINDING_BILL,
    },
    # Guías de remisión
    "gr": {
        "beta": _local_wsdl("billService.wsdl"),
        "prod": _local_wsdl("billService.wsdl"),
        "beta_url": "https://e-beta.sunat.gob.pe/ol-ti-itemision-guia-gem-beta/billService",
        "prod_url": "https://e-guiaremision.sunat.gob.pe/ol-ti-itemision-guia-gem/billService",
        "binding": _BINDING_BILL,
    },
    # Consulta de CDR
    "cdr": {
        "beta": _local_wsdl("billConsultService.wsdl"),
        "prod": _local_wsdl("billConsultService.wsdl"),
        "beta_url": "https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService",
        "prod_url": "https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService",
        "binding": _BINDING_CONSULT,
    },
}

# Mapeo tipo de documento → grupo WSDL SUNAT
_DOC_TYPE_TO_WSDL_GROUP: dict[str, str] = {
    "invoice":             "fe",
    "credit":              "fe",
    "debit":               "fe",
    "summary":             "fe",
    "voided":              "fe",
    "retention":           "re",
    "perception":          "re",
    "dispatch":            "gr",
    "dispatch2":           "gr",
    "purchase_settlement": "fe",
}

# Código SUNAT que indica CPE aceptado
_SUNAT_CODE_ACCEPTED = "0"

# Rango de códigos SUNAT de observación (aceptado con notas)
_OBSERVED_CODE_RANGE = range(2000, 4000)

# Código SUNAT que indica ticket aún en proceso
_TICKET_PENDING_CODE = "98"


# ---------------------------------------------------------------------------
# Registro de proveedores PSE/OSE (REST)
# ---------------------------------------------------------------------------
# Estructura de cada proveedor:
#   <env>         → dict de endpoints: token, generate, send, query
#   token_fields  → mapeo de (username, password) a campos del proveedor
#   token_key     → nombre del campo en la respuesta que contiene el Bearer token
#
# Para agregar un nuevo proveedor solo hay que agregar una entrada aquí.

_PSE_PROVIDERS: dict[str, dict] = {
    # Gior Technology (PSE + OSE)
    "gior": {
        "beta": {
            "token":    "https://beta-pse.giortechnology.com/service/api/auth/cpe/token",
            "generate": "https://beta-pse.giortechnology.com/service/api/cpe/generar",
            "send":     "https://beta-pse.giortechnology.com/service/api/cpe/enviar",
            "query":    "https://beta-pse.giortechnology.com/service/api/cpe/consultar",
        },
        "prod": {
            "token":    "https://pse.giortechnology.com/service/api/auth/cpe/token",
            "generate": "https://pse.giortechnology.com/service/api/cpe/generar",
            "send":     "https://pse.giortechnology.com/service/api/cpe/enviar",
            "query":    "https://pse.giortechnology.com/service/api/cpe/consultar",
        },
        "token_fields": {"username": "username", "password": "password"},
        "token_key":    "access_token",
    },
    # QPSE
    "qpse": {
        "beta": {
            "token":    "https://demo-cpe.qpse.pe/api/auth/cpe/token",
            "generate": "https://demo-cpe.qpse.pe/api/cpe/generar",
            "send":     "https://demo-cpe.qpse.pe/api/cpe/enviar",
            "query":    "https://demo-cpe.qpse.pe/api/cpe/consultar",
        },
        "prod": {
            "token":    "https://cpe.qpse.pe/api/auth/cpe/token",
            "generate": "https://cpe.qpse.pe/api/cpe/generar",
            "send":     "https://cpe.qpse.pe/api/cpe/enviar",
            "query":    "https://cpe.qpse.pe/api/cpe/consultar",
        },
        "token_fields": {"username": "username", "password": "password"},
        "token_key":    "access_token",
    },
    # SENDFact
    "sendfact": {
        "beta": {
            "token":    "https://demo-api.sendfact.com/api/auth/token",
            "generate": "https://demo-api.sendfact.com/api/cpe/sign",
            "send":     "https://demo-api.sendfact.com/api/cpe/send",
            "query":    "https://demo-api.sendfact.com/api/cpe/{file_name}/status",
        },
        "prod": {
            "token":    "https://api.sendfact.com/api/auth/token",
            "generate": "https://api.sendfact.com/api/cpe/sign",
            "send":     "https://api.sendfact.com/api/cpe/send",
            "query":    "https://api.sendfact.com/api/cpe/{file_name}/status",
        },
        "token_fields": {"username": "username", "password": "password"},
        "token_key":    "access_token",
    },
    # VALIDAPSE
    "validapse": {
        "beta": {
            "token":    "https://app.validapse.com/api/auth/cpe/token",
            "generate": "https://app.validapse.com/api/cpe/generar-demo",
            "send":     "https://app.validapse.com/api/cpe/enviar-demo",
            "query":    "https://app.validapse.com/api/cpe/consultar-demo",
        },
        "prod": {
            "token":    "https://app.validapse.com/api/auth/cpe/token",
            "generate": "https://app.validapse.com/api/cpe/generar",
            "send":     "https://app.validapse.com/api/cpe/enviar",
            "query":    "https://app.validapse.com/api/cpe/consultar",
        },
        "token_fields": {"username": "username", "password": "password"},
        "token_key":    "access_token",
    },
    # Proveedor personalizado: endpoints suministrados explícitamente en company_config
    # Llaves esperadas: pse_token_url, pse_send_url.  Opcionales: pse_generate_url, pse_query_url
    "custom": {
        "beta": None,
        "prod": None,
        "token_fields": {"username": "username", "password": "password"},
        "token_key":    "access_token",
    },
}


# ---------------------------------------------------------------------------
# Resultado normalizado de todos los senders
# ---------------------------------------------------------------------------

@dataclass
class SendResult:
    """
    Resultado normalizado retornado por todos los senders.

    Equivalente a BillResult / SummaryResult / StatusResult en PHP.
    """
    success: bool = False
    code: str = "-1"
    description: str = ""
    notes: list[str] = field(default_factory=list)
    # CDR como base64 (recibido de SUNAT)
    cdr_b64: Optional[str] = None
    # Ticket para flujos asíncronos (resúmenes / bajas)
    ticket: Optional[str] = None
    # XML firmado devuelto por el PSE (solo flujo PSE)
    xml_signed_b64: Optional[str] = None
    # Hash del documento devuelto por el PSE (solo flujo PSE)
    hash_code: Optional[str] = None


# ---------------------------------------------------------------------------
# WSDLs locales bundleados (igual que CoreFacturalo/WS/Client/Resources/wsdl/)
# CoreFacturalo carga los WSDLs desde disco — nunca los descarga en runtime.
# ---------------------------------------------------------------------------

from pathlib import Path as _Path

_WSDL_DIR = str(_Path(__file__).parent.parent / "wsdl")


def _local_wsdl(filename: str) -> str:
    """Retorna la ruta absoluta al WSDL local bundleado."""
    import os
    return os.path.join(_WSDL_DIR, filename)


# ---------------------------------------------------------------------------
# SunatSoapSender — envío directo a SUNAT vía SOAP con WSSE
# ---------------------------------------------------------------------------

class SunatSoapSender:
    """
    Envía comprobantes a SUNAT mediante SOAP con autenticación WSSE UsernameToken.

    Consolida la lógica de BillSender, SummarySender, ExtService y
    ConsultCdrService de CoreFacturalo/WS/.
    """

    def __init__(
        self,
        username: str,
        password: str,
        wsdl_url: str,
        service_url: str = "",
        binding_qname: str = "",
        timeout: int = 30,
    ) -> None:
        if not _ZEEP_AVAILABLE:
            raise RuntimeError("zeep no está instalado. Ejecutar: pip install zeep")
        # Usa WSDL local (igual que CoreFacturalo) para evitar 401 en SUNAT beta.
        # Equivalente a new SoapClient($local_wsdl, ['location' => $url]) de PHP.
        transport = Transport(operation_timeout=timeout, timeout=timeout)
        settings = ZeepSettings(strict=False, xml_huge_tree=True)
        # Autenticación WSSE equivalente a WsSecurityHeader.php
        wsse = UsernameToken(username, password)
        self._client = ZeepClient(wsdl_url, transport=transport, settings=settings, wsse=wsse)
        # create_service crea un proxy con la URL real del servicio SUNAT,
        # sobrescribiendo la URL embebida en el WSDL local (equivalente a __setLocation de PHP).
        if service_url and binding_qname:
            self._service = self._client.create_service(binding_qname, service_url)
        else:
            self._service = self._client.service
        self._service_url = service_url
        logger.debug("SunatSoapSender inicializado: wsdl=%s service=%s", wsdl_url, service_url)

    def send_bill(self, filename: str, xml_signed_bytes: bytes) -> SendResult:
        """
        Envía un comprobante individual a SUNAT (factura, boleta, NC, ND, etc.).

        SUNAT requiere el XML comprimido en ZIP con el mismo nombre base.
        Equivalente a BillSender::send() en PHP.
        """
        result = SendResult()
        zip_bytes = _zip_xml(filename + ".xml", xml_signed_bytes)
        logger.info("Enviando bill a SUNAT: %s", filename)
        try:
            response = self._service.sendBill(
                fileName=filename + ".zip",
                contentFile=zip_bytes,  # xs:base64Binary → zeep codifica bytes raw a base64
            )
            _fill_from_bill_response(result, response)
        except Exception as exc:
            result.code = _extract_soap_fault_code(exc)
            result.description = str(exc)
            logger.exception("Error en sendBill SUNAT: %s", filename)
        return result

    def send_summary(self, filename: str, xml_signed_bytes: bytes) -> SendResult:
        """
        Envía resumen diario (RC) o comunicación de baja (RA) a SUNAT.

        Flujo asíncrono: SUNAT devuelve un ticket; consultar con get_status().
        Equivalente a SummarySender::send() en PHP.
        """
        result = SendResult()
        zip_bytes = _zip_xml(filename + ".xml", xml_signed_bytes)
        logger.info("Enviando summary a SUNAT: %s", filename)
        try:
            response = self._service.sendSummary(
                fileName=filename + ".zip",
                contentFile=zip_bytes,  # xs:base64Binary → zeep codifica bytes raw a base64
            )
            ticket = getattr(response, "ticket", None)
            if ticket:
                result.success = True
                result.ticket = str(ticket)
                result.code = "0"
                result.description = f"Ticket recibido: {ticket}"
            else:
                result.description = "SUNAT no retornó un ticket"
        except Exception as exc:
            result.code = _extract_soap_fault_code(exc)
            result.description = str(exc)
            logger.exception("Error en sendSummary SUNAT: %s", filename)
        return result

    def get_status(self, ticket: str) -> SendResult:
        """
        Consulta el estado de un ticket de resumen o baja.

        Retorna el CDR si el procesamiento terminó (código 0 o 99).
        Equivalente a ExtService::getStatus() en PHP.
        """
        result = SendResult()
        logger.info("Consultando ticket SUNAT: %s", ticket)
        try:
            response = self._service.getStatus(ticket=ticket)
            status = getattr(response, "status", response)
            code = str(getattr(status, "statusCode", "-1")).strip()
            result.code = code
            if code in ("0", "99"):
                # Procesamiento finalizado — extraer CDR
                cdr_b64_str = getattr(status, "content", None)
                if cdr_b64_str:
                    _fill_from_cdr_zip(result, str(cdr_b64_str))
                    result.success = True
            elif code == _TICKET_PENDING_CODE:
                result.description = "Comprobante en proceso (ticket pendiente)"
            else:
                _enrich_with_error_message(result, code)
        except Exception as exc:
            result.code = _extract_soap_fault_code(exc)
            result.description = str(exc)
            logger.exception("Error consultando ticket SUNAT: %s", ticket)
        return result

    def consult_cdr(
        self,
        ruc: str,
        doc_type_id: str,
        series: str,
        number: str,
    ) -> SendResult:
        """
        Consulta el CDR de un comprobante por sus datos de identificación.

        Equivalente a ConsultCdrService::getStatusCdr() en PHP.
        Requiere instanciar con el WSDL del servicio de consulta (billConsultService).
        """
        result = SendResult()
        logger.info("Consultando CDR: %s-%s-%s-%s", ruc, doc_type_id, series, number)
        try:
            response = self._service.getStatusCdr(
                rucComprobante=ruc,
                tipoComprobante=doc_type_id,
                serieComprobante=series,
                numeroComprobante=number,
            )
            status_cdr = getattr(response, "statusCdr", None)
            if status_cdr:
                content = getattr(status_cdr, "content", None)
                if content:
                    _fill_from_cdr_zip(result, str(content))
                    result.success = True
            else:
                result.description = "No se encontró CDR para el comprobante consultado"
        except Exception as exc:
            result.code = _extract_soap_fault_code(exc)
            result.description = str(exc)
            logger.exception("Error consultando CDR SUNAT")
        return result


# ---------------------------------------------------------------------------
# PseRestSender — PSE: firma XML sin firmar vía REST
# ---------------------------------------------------------------------------

class PseRestSender:
    """
    Proveedor de Servicios Electrónicos (PSE) vía REST.

    Flujo:
      1. pipeline llama sign_xml(filename, xml_unsigned) → PSE firma
      2. PSE retorna xml_signed_b64 + hash_code en SendResult
      3. pipeline usa SunatSoapSender.send_bill con el xml_signed recibido

    Equivalente a ServiceSendFact.php en CoreFacturalo/PseService/.
    """

    def __init__(
        self,
        username: str,
        password: str,
        endpoints: dict[str, str],
        token_key: str = "access_token",
        token_fields: Optional[dict[str, str]] = None,
    ) -> None:
        if not _HTTPX_AVAILABLE:
            raise RuntimeError("httpx no está instalado. Ejecutar: pip install httpx")
        self._username = username
        self._password = password
        self._endpoints = endpoints
        self._token_key = token_key
        self._token_fields = token_fields or {"username": "username", "password": "password"}
        self._cached_token: Optional[str] = None

    def sign_xml(self, filename: str, xml_unsigned_bytes: bytes) -> SendResult:
        """
        Envía el XML sin firmar al PSE y recibe el XML firmado + hash.

        Equivalente a ServiceSendFact::sendXml() en PHP.
        """
        result = SendResult()
        token = self._get_token()
        url = self._endpoints.get("generate", "")
        if not url:
            result.description = "Endpoint 'generate' no configurado para este proveedor PSE"
            return result

        body = {
            "file_name": filename,
            "file_content": base64.b64encode(xml_unsigned_bytes).decode("ascii"),
        }
        logger.info("Enviando XML al PSE para firma: %s, url=%s", filename, url)
        try:
            response = httpx.post(
                url, json=body,
                headers={"Authorization": f"Bearer {token}"},
                verify=False,       # noqa: S501 — PSE pueden usar certs autofirmados
                timeout=60.0,
            )
            data = response.json()
            if response.status_code == 200:
                result.success = True
                result.code = "0"
                # Normalizar campo: distintos PSEs usan nombres distintos
                result.xml_signed_b64 = (
                    data.get("xml")
                    or data.get("xml_signed")
                    or data.get("file_content")
                )
                result.hash_code = data.get("hash_code") or data.get("hash")
                result.description = data.get("message", "Firmado exitosamente")
            else:
                result.code = str(response.status_code)
                result.description = _extract_pse_error(data)
                logger.warning("PSE sign_xml error %s: %s", response.status_code, data)
        except Exception as exc:
            result.description = str(exc)
            logger.exception("Error en PseRestSender.sign_xml: %s", filename)
        return result

    def query_status(self, filename: str) -> SendResult:
        """
        Consulta el estado de un comprobante en el PSE.

        SENDFact y otros proveedores usan {file_name} en la URL del query endpoint.
        """
        result = SendResult()
        token = self._get_token()
        query_url = self._endpoints.get("query", "")
        url = query_url.replace("{file_name}", filename)
        if not url:
            result.description = "Endpoint 'query' no configurado para este proveedor PSE"
            return result

        logger.info("Consultando estado PSE: %s", filename)
        try:
            response = httpx.get(
                url,
                headers={"Authorization": f"Bearer {token}"},
                verify=False,       # noqa: S501
                timeout=30.0,
            )
            data = response.json()
            if response.status_code == 200:
                result.success = True
                result.code = str(data.get("estado", data.get("status", "0")))
                result.description = data.get("message", "")
                cdr = data.get("cdr") or data.get("cdr_b64")
                if cdr:
                    result.cdr_b64 = cdr
            else:
                result.code = str(response.status_code)
                result.description = _extract_pse_error(data)
        except Exception as exc:
            result.description = str(exc)
            logger.exception("Error en PseRestSender.query_status: %s", filename)
        return result

    def _get_token(self) -> str:
        """
        Obtiene el Bearer token del PSE.

        El token se cachea en memoria durante la vida de la instancia.
        Para tokens con expiración corta, crear instancias nuevas por request
        o refrescar usando expires_in de la respuesta del token.
        """
        if self._cached_token:
            return self._cached_token

        url = self._endpoints["token"]
        user_field = self._token_fields["username"]
        pass_field = self._token_fields["password"]
        body = {user_field: self._username, pass_field: self._password}

        logger.debug("Obteniendo token PSE: %s", url)
        try:
            response = httpx.post(
                url, json=body,
                headers={"Content-Type": "application/json"},
                verify=False,       # noqa: S501
                timeout=30.0,
            )
            response.raise_for_status()
            data = response.json()
            token = data.get(self._token_key)
            if not token:
                raise ValueError(
                    f"Token PSE no encontrado. "
                    f"Clave esperada: '{self._token_key}'. "
                    f"Campos recibidos: {list(data.keys())}"
                )
            self._cached_token = token
            return token
        except Exception as exc:
            raise RuntimeError(
                f"Error obteniendo token PSE desde {url}: {exc}"
            ) from exc


# ---------------------------------------------------------------------------
# OseRestSender — OSE: envía XML firmado y retorna CDR vía REST
# ---------------------------------------------------------------------------

class OseRestSender:
    """
    Operador de Servicios Electrónicos (OSE) vía REST.

    Flujo:
      1. pipeline firma el XML localmente con XmlSigner
      2. pipeline llama send_bill(filename, xml_signed) → OSE envía a SUNAT
      3. OSE retorna CDR en la misma respuesta (síncrono para facturas)
      4. Para resúmenes/bajas puede retornar ticket → usar get_status()

    Equivalente a ServiceOseSendFact.php en CoreFacturalo/PseService/.
    """

    def __init__(
        self,
        username: str,
        password: str,
        endpoints: dict[str, str],
        token_key: str = "access_token",
        token_fields: Optional[dict[str, str]] = None,
    ) -> None:
        if not _HTTPX_AVAILABLE:
            raise RuntimeError("httpx no está instalado. Ejecutar: pip install httpx")
        self._username = username
        self._password = password
        self._endpoints = endpoints
        self._token_key = token_key
        self._token_fields = token_fields or {"username": "username", "password": "password"}
        self._cached_token: Optional[str] = None

    def send_bill(self, filename: str, xml_signed_bytes: bytes) -> SendResult:
        """
        Envía un comprobante individual al OSE (factura, boleta, NC, ND, etc.).

        Equivalente a ServiceOseSendFact::sendXmlSigned(hasSummary=false) en PHP.
        """
        return self._send(filename, xml_signed_bytes, is_summary=False)

    def send_summary(self, filename: str, xml_signed_bytes: bytes) -> SendResult:
        """
        Envía un resumen diario o comunicación de baja al OSE.

        Equivalente a ServiceOseSendFact::sendXmlSigned(hasSummary=true) en PHP.
        """
        return self._send(filename, xml_signed_bytes, is_summary=True)

    def get_status(self, ticket: str, filename: str = "") -> SendResult:
        """Consulta el estado de un ticket emitido por el OSE."""
        result = SendResult()
        token = self._get_token()
        query_url = self._endpoints.get("query", "")
        url = query_url.replace("{file_name}", filename)
        if not url:
            result.description = "Endpoint 'query' no configurado para este proveedor OSE"
            return result

        logger.info("Consultando estado OSE ticket: %s", ticket)
        try:
            response = httpx.post(
                url, json={"ticket": ticket},
                headers={"Authorization": f"Bearer {token}"},
                verify=False,       # noqa: S501
                timeout=30.0,
            )
            data = response.json()
            if response.status_code in (200, 202):
                result.success = True
                result.code = str(data.get("estado", data.get("status", "0")))
                result.description = data.get("message", "")
                cdr = data.get("cdr")
                if cdr:
                    result.cdr_b64 = cdr
            else:
                result.code = str(response.status_code)
                result.description = _extract_pse_error(data)
        except Exception as exc:
            result.description = str(exc)
            logger.exception("Error en OseRestSender.get_status: %s", ticket)
        return result

    def _send(self, filename: str, xml_signed_bytes: bytes, is_summary: bool) -> SendResult:
        result = SendResult()
        token = self._get_token()
        url = self._endpoints.get("send", "")
        if not url:
            result.description = "Endpoint 'send' no configurado para este proveedor OSE"
            return result

        body = {
            "file_name": filename,
            "file_content": base64.b64encode(xml_signed_bytes).decode("ascii"),
        }
        logger.info("Enviando XML firmado al OSE: %s, url=%s", filename, url)
        try:
            response = httpx.post(
                url, json=body,
                headers={"Authorization": f"Bearer {token}"},
                verify=False,       # noqa: S501
                timeout=60.0,
            )
            data = response.json()
            if response.status_code in (200, 202):
                result.success = True
                result.code = str(data.get("estado", data.get("status", "0")))
                result.description = data.get("message", "")
                result.xml_signed_b64 = data.get("xml")
                cdr = data.get("cdr")
                if cdr:
                    result.cdr_b64 = cdr
                if is_summary:
                    result.ticket = data.get("ticket")
                for key in ("observations", "errors"):
                    items = data.get(key) or []
                    if isinstance(items, list):
                        result.notes.extend(str(x) for x in items)
                    elif items:
                        result.notes.append(str(items))
            else:
                result.code = str(response.status_code)
                result.description = _extract_pse_error(data)
                logger.warning("OSE send error %s: %s", response.status_code, data)
        except Exception as exc:
            result.description = str(exc)
            logger.exception("Error en OseRestSender._send: %s", filename)
        return result

    def _get_token(self) -> str:
        if self._cached_token:
            return self._cached_token

        url = self._endpoints["token"]
        user_field = self._token_fields["username"]
        pass_field = self._token_fields["password"]
        body = {user_field: self._username, pass_field: self._password}

        logger.debug("Obteniendo token OSE: %s", url)
        try:
            response = httpx.post(
                url, json=body,
                headers={"Content-Type": "application/json"},
                verify=False,       # noqa: S501
                timeout=30.0,
            )
            response.raise_for_status()
            data = response.json()
            token = data.get(self._token_key)
            if not token:
                raise ValueError(
                    f"Token OSE no encontrado. "
                    f"Clave esperada: '{self._token_key}'. "
                    f"Campos recibidos: {list(data.keys())}"
                )
            self._cached_token = token
            return token
        except Exception as exc:
            raise RuntimeError(
                f"Error obteniendo token OSE desde {url}: {exc}"
            ) from exc


# ---------------------------------------------------------------------------
# Funciones de fábrica
# ---------------------------------------------------------------------------

def resolve_wsdl(doc_type: str, is_demo: bool) -> str:
    """
    Retorna el WSDL correcto para el tipo de documento y entorno.

    Equivalente a SunatEndpoints en PHP.
    """
    group = _DOC_TYPE_TO_WSDL_GROUP.get(doc_type, "fe")
    env = "beta" if is_demo else "prod"
    return _SUNAT_WSDL[group][env]


def get_pse_endpoints(
    provider: str,
    is_demo: bool,
    custom_config: Optional[dict] = None,
) -> dict[str, str]:
    """
    Retorna los endpoints del proveedor PSE/OSE según el entorno.

    Para provider='custom', extrae las URLs de custom_config:
      pse_token_url (requerido), pse_send_url (requerido),
      pse_generate_url (opcional), pse_query_url (opcional).

    Para agregar un nuevo proveedor conocido: agregar entrada en _PSE_PROVIDERS.
    """
    if provider not in _PSE_PROVIDERS:
        known = ", ".join(_PSE_PROVIDERS.keys())
        raise ValueError(
            f"Proveedor PSE/OSE desconocido: '{provider}'. "
            f"Disponibles: {known}. "
            f"Para un proveedor nuevo usa 'custom' con las URLs en company_config."
        )

    env = "beta" if is_demo else "prod"
    prov = _PSE_PROVIDERS[provider]

    if provider == "custom" or prov.get(env) is None:
        if not custom_config:
            raise ValueError(
                "Proveedor 'custom' requiere las siguientes URL en company_config: "
                "pse_token_url, pse_send_url."
            )
        return {
            "token":    custom_config["pse_token_url"],
            "generate": custom_config.get("pse_generate_url", ""),
            "send":     custom_config["pse_send_url"],
            "query":    custom_config.get("pse_query_url", ""),
        }
    return dict(prov[env])


def build_sunat_sender(doc_type: str, company_config: dict) -> SunatSoapSender:
    """
    Construye un SunatSoapSender para el tipo de documento dado.
    Usa WSDL local (como CoreFacturalo) y sobreescribe la URL de servicio
    solo cuando la URL embebida en el WSDL no coincide con el endpoint objetivo.

    billService.wsdl tiene URL beta embebida → para fe+beta usar client.service directamente
    (create_service rompe el parseo de SOAP faults en zeep).
    Para producción o grupos re/gr siempre se sobreescribe con create_service.
    """
    is_demo = company_config.get("sunat_mode", "demo") == "demo"
    group = _DOC_TYPE_TO_WSDL_GROUP.get(doc_type, "fe")
    env = "beta" if is_demo else "prod"
    entry = _SUNAT_WSDL[group]

    # billService.wsdl embebe la URL de beta FE.
    # Si group=="fe" y estamos en beta → la URL embebida ya es la correcta, sin override.
    # En cualquier otro caso → create_service con la URL del grupo/entorno correcto.
    wsdl_embedded_is_target = (group == "fe" and is_demo)

    return SunatSoapSender(
        username=company_config["soap_username"],
        password=company_config["soap_password"],
        wsdl_url=entry[env],
        service_url="" if wsdl_embedded_is_target else entry[env + "_url"],
        binding_qname="" if wsdl_embedded_is_target else entry["binding"],
    )


def build_sunat_cdr_sender(company_config: dict) -> SunatSoapSender:
    """Construye un SunatSoapSender para el servicio de consulta de CDR.

    billConsultService.wsdl embebe la URL de producción, que coincide con beta/prod
    (mismo endpoint). No requiere create_service.
    """
    is_demo = company_config.get("sunat_mode", "demo") == "demo"
    env = "beta" if is_demo else "prod"
    entry = _SUNAT_WSDL["cdr"]
    # La URL embebida en billConsultService.wsdl coincide con beta_url y prod_url → sin override
    return SunatSoapSender(
        username=company_config["soap_username"],
        password=company_config["soap_password"],
        wsdl_url=entry[env],
        service_url="",
        binding_qname="",
    )


def build_pse_sender(company_config: dict) -> PseRestSender:
    """Construye un PseRestSender según la configuración de la compañía."""
    provider = company_config.get("pse_provider", "gior")
    is_demo = company_config.get("sunat_mode", "demo") == "demo"
    endpoints = get_pse_endpoints(provider, is_demo, company_config)
    prov_meta = _PSE_PROVIDERS.get(provider, _PSE_PROVIDERS["custom"])
    return PseRestSender(
        username=company_config["pse_username"],
        password=company_config["pse_password"],
        endpoints=endpoints,
        token_key=prov_meta.get("token_key", "access_token"),
        token_fields=prov_meta.get("token_fields"),
    )


def build_ose_sender(company_config: dict) -> OseRestSender:
    """
    Construye un OseRestSender según la configuración de la compañía.

    Para OSE se usan credenciales SOL (soap_username/soap_password), ya que el
    OSE actúa en nombre de la empresa directamente ante SUNAT.
    """
    provider = company_config.get("pse_provider", "gior")
    is_demo = company_config.get("sunat_mode", "demo") == "demo"
    endpoints = get_pse_endpoints(provider, is_demo, company_config)
    prov_meta = _PSE_PROVIDERS.get(provider, _PSE_PROVIDERS["custom"])
    return OseRestSender(
        username=company_config["soap_username"],
        password=company_config["soap_password"],
        endpoints=endpoints,
        token_key=prov_meta.get("token_key", "access_token"),
        token_fields=prov_meta.get("token_fields"),
    )


def list_pse_providers() -> list[str]:
    """Retorna la lista de slugs de proveedores PSE/OSE soportados."""
    return list(_PSE_PROVIDERS.keys())


# ---------------------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------------------

def _zip_xml(filename: str, xml_bytes: bytes) -> bytes:
    """
    Comprime el XML en un ZIP en memoria.

    SUNAT requiere el XML dentro de un ZIP con el mismo nombre base.
    Ej: 20600000001-01-F001-1.xml → 20600000001-01-F001-1.zip
    """
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        zf.writestr(filename, xml_bytes)
    return buffer.getvalue()


def _extract_from_zip(zip_bytes: bytes) -> Optional[bytes]:
    """Extrae el primer archivo XML del ZIP del CDR."""
    try:
        with zipfile.ZipFile(io.BytesIO(zip_bytes)) as zf:
            for name in zf.namelist():
                if name.endswith(".xml"):
                    return zf.read(name)
    except Exception:
        pass
    return None


def _parse_cdr_xml(cdr_xml: bytes) -> tuple[str, str, list[str]]:
    """
    Parsea el ApplicationResponse XML del CDR de SUNAT.

    Retorna (response_code, description, notas).
    Equivalente a DomCdrReader::getCdrResponse() en PHP.
    """
    try:
        from lxml import etree
        root = etree.fromstring(cdr_xml)
        ns = {"cbc": "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"}
        code = (root.findtext(".//cbc:ResponseCode", namespaces=ns) or "-1").strip()
        description = (root.findtext(".//cbc:Description", namespaces=ns) or "").strip()
        notes = [
            n.text.strip()
            for n in root.findall(".//cbc:Note", namespaces=ns)
            if n.text
        ]
        return code, description, notes
    except Exception as exc:
        logger.warning("Error parseando CDR XML: %s", exc)
        return "-1", str(exc), []


def _fill_from_bill_response(result: SendResult, soap_response: object) -> None:
    """Rellena SendResult a partir de la respuesta SOAP de sendBill.

    zeep puede devolver la respuesta de dos formas:
    - bytes directos: cuando el tipo de respuesta es xs:base64Binary (ya decodificado)
    - objeto con atributo applicationResponse: si el WSDL envuelve la respuesta

    En ambos casos los bytes recibidos son el CDR ZIP ya decodificado (no base64).
    Los codificamos a base64 para almacenamiento uniforme.
    """
    try:
        # caso 1: zeep desenvuelve automáticamente → response ES los bytes del CDR
        if isinstance(soap_response, bytes):
            cdr_b64_str = base64.b64encode(soap_response).decode("ascii")
        else:
            # caso 2: objeto con atributo applicationResponse
            cdr_raw = getattr(soap_response, "applicationResponse", None)
            if cdr_raw is None:
                return
            if isinstance(cdr_raw, bytes):
                cdr_b64_str = base64.b64encode(cdr_raw).decode("ascii")
            else:
                cdr_b64_str = str(cdr_raw)
        if cdr_b64_str:
            _fill_from_cdr_zip(result, cdr_b64_str)
    except Exception as exc:
        result.description = str(exc)


def _fill_from_cdr_zip(result: SendResult, cdr_b64_str: str) -> None:
    """Extrae, parsea el CDR ZIP en base64 y rellena el SendResult."""
    result.cdr_b64 = cdr_b64_str
    try:
        cdr_zip_bytes = base64.b64decode(cdr_b64_str)
        cdr_xml = _extract_from_zip(cdr_zip_bytes)
        if cdr_xml:
            code, description, notes = _parse_cdr_xml(cdr_xml)
            result.code = code
            result.description = description
            result.notes = notes
            try:
                code_int = int(code)
                result.success = (code_int == 0 or code_int in _OBSERVED_CODE_RANGE)
            except ValueError:
                result.success = False
    except Exception as exc:
        logger.warning("No se pudo parsear el CDR ZIP: %s", exc)
        result.description = str(exc)


def _enrich_with_error_message(result: SendResult, code: str) -> None:
    """Agrega el mensaje de error SUNAT al resultado según el código del catálogo."""
    result.code = code
    try:
        from app.services.sunat_errors import get_error_message
        message = get_error_message(code)
        result.description = message or f"Error SUNAT código {code}"
    except ImportError:
        result.description = f"Error SUNAT código {code}"


def _extract_soap_fault_code(exc: Exception) -> str:
    """Extrae el código numérico de un SoapFault de zeep.

    zeep.exceptions.Fault usa el atributo .code (no .faultcode).
    Ej: code='soap-env:Client.3244' → retorna '3244'.
    """
    # zeep usa 'code', Python's xml.etree usa 'faultcode'
    fault_code = str(
        getattr(exc, "code", None) or getattr(exc, "faultcode", None) or ""
    )
    digits = re.sub(r"[^0-9]", "", fault_code)
    return digits if digits else "-1"


def _extract_pse_error(data: object) -> str:
    """Extrae el mensaje de error de una respuesta JSON de PSE/OSE."""
    if isinstance(data, dict):
        errors = data.get("errors")
        if isinstance(errors, list) and errors:
            return "; ".join(str(e) for e in errors)
        return (
            data.get("message")
            or data.get("error")
            or data.get("descripcion")
            or str(data)
        )
    return str(data)
