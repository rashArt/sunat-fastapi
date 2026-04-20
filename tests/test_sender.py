"""
Tests unitarios para app/services/sender.py — Fase 4.

Cubre:
  - Helpers internos (_zip_xml, _extract_from_zip, _parse_cdr_xml, etc.)
  - resolve_wsdl / get_pse_endpoints / list_pse_providers
  - SunatSoapSender (mocked zeep)
  - PseRestSender (mocked httpx)
  - OseRestSender (mocked httpx)
  - Funciones de fábrica (build_* con mocks de zeep/httpx)
"""
from __future__ import annotations

import base64
import io
import os
import zipfile
from unittest.mock import MagicMock, patch

import pytest

from app.services.sender import (
    SendResult,
    SunatSoapSender,
    PseRestSender,
    OseRestSender,
    _extract_from_zip,
    _extract_pse_error,
    _extract_soap_fault_code,
    _fill_from_cdr_zip,
    _parse_cdr_xml,
    _zip_xml,
    build_ose_sender,
    build_pse_sender,
    build_sunat_cdr_sender,
    build_sunat_sender,
    get_pse_endpoints,
    list_pse_providers,
    resolve_wsdl,
)


# ---------------------------------------------------------------------------
# Fixtures comunes
# ---------------------------------------------------------------------------

XML_SAMPLE = b'<?xml version="1.0"?><Invoice><ID>F001-1</ID></Invoice>'
FILENAME = "20600000001-01-F001-1"

CDR_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ResponseCode>0</cbc:ResponseCode>
  <cbc:Description>La Factura numero F001-1, ha sido aceptada</cbc:Description>
  <cbc:Note>Informacion adicional 1</cbc:Note>
</ApplicationResponse>"""

CDR_XML_OBSERVED = CDR_XML.replace(b"<cbc:ResponseCode>0", b"<cbc:ResponseCode>2000")

@pytest.fixture
def cdr_zip_b64() -> str:
    """ZIP en memoria con el XML del CDR, codificado en base64."""
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("R-20600000001-01-F001-1.xml", CDR_XML)
    return base64.b64encode(buf.getvalue()).decode("ascii")


@pytest.fixture
def company_config_sunat() -> dict:
    return {
        "legal_number": "20600000001",
        "legal_name": "Empresa Test S.A.C.",
        "sunat_mode": "demo",
        "soap_send_type": "sunat",
        "soap_username": "MODDATOS",
        "soap_password": "moddatos",
    }


@pytest.fixture
def company_config_pse() -> dict:
    return {
        "legal_number": "20600000001",
        "legal_name": "Empresa Test S.A.C.",
        "sunat_mode": "demo",
        "soap_send_type": "pse",
        "soap_username": "MODDATOS",
        "soap_password": "moddatos",
        "pse_provider": "gior",
        "pse_username": "usertest",
        "pse_password": "passtest",
    }


@pytest.fixture
def company_config_custom() -> dict:
    return {
        "legal_number": "20600000001",
        "legal_name": "Empresa Test S.A.C.",
        "sunat_mode": "demo",
        "soap_send_type": "pse",
        "soap_username": "MODDATOS",
        "soap_password": "moddatos",
        "pse_provider": "custom",
        "pse_username": "usertest",
        "pse_password": "passtest",
        "pse_token_url":    "https://custom.example.com/token",
        "pse_generate_url": "https://custom.example.com/sign",
        "pse_send_url":     "https://custom.example.com/send",
        "pse_query_url":    "https://custom.example.com/query",
    }


# ---------------------------------------------------------------------------
# Tests: helpers
# ---------------------------------------------------------------------------

class TestZipXml:
    def test_crea_zip_valido(self):
        resultado = _zip_xml(FILENAME + ".xml", XML_SAMPLE)
        with zipfile.ZipFile(io.BytesIO(resultado)) as zf:
            nombres = zf.namelist()
        assert FILENAME + ".xml" in nombres

    def test_contenido_preservado(self):
        resultado = _zip_xml(FILENAME + ".xml", XML_SAMPLE)
        with zipfile.ZipFile(io.BytesIO(resultado)) as zf:
            contenido = zf.read(FILENAME + ".xml")
        assert contenido == XML_SAMPLE


class TestExtractFromZip:
    def test_extrae_xml(self):
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w") as zf:
            zf.writestr("doc.xml", XML_SAMPLE)
        raw = _extract_from_zip(buf.getvalue())
        assert raw == XML_SAMPLE

    def test_retorna_none_si_no_xml(self):
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w") as zf:
            zf.writestr("archivo.txt", b"texto")
        assert _extract_from_zip(buf.getvalue()) is None

    def test_retorna_none_si_bytes_invalidos(self):
        assert _extract_from_zip(b"datos_invalidos") is None


class TestParseCdrXml:
    def test_parsea_aceptado(self):
        code, desc, notes = _parse_cdr_xml(CDR_XML)
        assert code == "0"
        assert "aceptada" in desc.lower()
        assert len(notes) == 1
        assert "Informacion adicional 1" in notes[0]

    def test_parsea_observado(self):
        code, _, _ = _parse_cdr_xml(CDR_XML_OBSERVED)
        assert code == "2000"

    def test_retorna_minus1_si_xml_invalido(self):
        code, _, _ = _parse_cdr_xml(b"<malformado")
        assert code == "-1"


class TestFillFromCdrZip:
    def test_rellena_resultado_aceptado(self, cdr_zip_b64):
        result = SendResult()
        _fill_from_cdr_zip(result, cdr_zip_b64)
        assert result.code == "0"
        assert result.success is True
        assert result.cdr_b64 == cdr_zip_b64
        assert len(result.notes) == 1

    def test_rellena_resultado_observado(self):
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
            zf.writestr("R-doc.xml", CDR_XML_OBSERVED)
        cdr_b64 = base64.b64encode(buf.getvalue()).decode("ascii")

        result = SendResult()
        _fill_from_cdr_zip(result, cdr_b64)
        assert result.code == "2000"
        assert result.success is True  # 2000 ∈ rango observados (2000-3999)


class TestExtractSoapFaultCode:
    def test_extrae_codigo_numerico(self):
        exc = Exception()
        exc.faultcode = "EXP:0109"  # type: ignore[attr-defined]
        assert _extract_soap_fault_code(exc) == "0109"

    def test_retorna_menos1_sin_codigo(self):
        assert _extract_soap_fault_code(Exception("sin código")) == "-1"


class TestExtractPseError:
    def test_usa_campo_message(self):
        assert _extract_pse_error({"message": "Error X"}) == "Error X"

    def test_usa_campo_errors_lista(self):
        msg = _extract_pse_error({"errors": ["E1", "E2"]})
        assert "E1" in msg and "E2" in msg

    def test_fallback_a_str(self):
        assert "custom" in _extract_pse_error({"custom": "field"})


# ---------------------------------------------------------------------------
# Tests: funciones de resolución
# ---------------------------------------------------------------------------

class TestResolveWsdl:
    """resolve_wsdl() ahora retorna rutas locales (WSDL bundleado como en CoreFacturalo)."""

    @pytest.mark.parametrize("doc_type", ["invoice", "credit", "debit", "summary", "voided", "purchase_settlement"])
    def test_fe_retorna_wsdl_local(self, doc_type):
        path = resolve_wsdl(doc_type, is_demo=True)
        assert path.endswith("billService.wsdl")
        assert os.path.isabs(path)

    @pytest.mark.parametrize("doc_type", ["retention", "perception"])
    def test_re_retorna_wsdl_local(self, doc_type):
        path = resolve_wsdl(doc_type, is_demo=True)
        assert path.endswith("billService.wsdl")

    @pytest.mark.parametrize("doc_type", ["dispatch", "dispatch2"])
    def test_gr_retorna_wsdl_local(self, doc_type):
        path = resolve_wsdl(doc_type, is_demo=True)
        assert path.endswith("billService.wsdl")

    def test_beta_y_prod_misma_ruta_local(self):
        # Ambos entornos usan el mismo archivo WSDL local; la URL real se setea aparte
        beta = resolve_wsdl("invoice", is_demo=True)
        prod = resolve_wsdl("invoice", is_demo=False)
        assert beta == prod
        assert "billService" in beta

    def test_tipo_desconocido_cae_en_fe(self):
        path = resolve_wsdl("tipo_raro", is_demo=True)
        assert path.endswith("billService.wsdl")


class TestGetPseEndpoints:
    @pytest.mark.parametrize("provider", ["gior", "qpse", "sendfact", "validapse"])
    def test_proveedor_conocido_beta(self, provider):
        endpoints = get_pse_endpoints(provider, is_demo=True)
        assert "token" in endpoints
        assert "send" in endpoints

    @pytest.mark.parametrize("provider", ["gior", "qpse", "sendfact", "validapse"])
    def test_proveedor_conocido_prod(self, provider):
        endpoints = get_pse_endpoints(provider, is_demo=False)
        assert "beta" not in endpoints["token"] or provider == "validapse"

    def test_proveedor_custom_con_config(self, company_config_custom):
        endpoints = get_pse_endpoints("custom", is_demo=True, custom_config=company_config_custom)
        assert endpoints["token"] == "https://custom.example.com/token"
        assert endpoints["send"] == "https://custom.example.com/send"

    def test_proveedor_custom_sin_config_lanza_error(self):
        with pytest.raises(ValueError, match="pse_token_url"):
            get_pse_endpoints("custom", is_demo=True)

    def test_proveedor_desconocido_lanza_error(self):
        with pytest.raises(ValueError, match="desconocido"):
            get_pse_endpoints("noexiste", is_demo=True)


class TestListPseProviders:
    def test_incluye_todos_los_conocidos(self):
        providers = list_pse_providers()
        for slug in ("gior", "qpse", "sendfact", "validapse", "custom"):
            assert slug in providers


# ---------------------------------------------------------------------------
# Tests: SunatSoapSender (zeep mockeado)
# ---------------------------------------------------------------------------

def _make_sunat_sender(wsdl_url: str = "https://e-beta.sunat.gob.pe/fake?wsdl") -> SunatSoapSender:
    """Crea SunatSoapSender con zeep completamente mockeado."""
    with patch("app.services.sender._ZEEP_AVAILABLE", True), \
         patch("app.services.sender.ZeepClient") as MockClient, \
         patch("app.services.sender.Transport"), \
         patch("app.services.sender.ZeepSettings"), \
         patch("app.services.sender.UsernameToken"):
        sender = SunatSoapSender("user", "pass", wsdl_url)
        sender._client = MockClient.return_value
        return sender


class TestSunatSoapSenderSendBill:
    def test_send_bill_exitoso(self, cdr_zip_b64):
        sender = _make_sunat_sender()
        mock_response = MagicMock()
        mock_response.applicationResponse = cdr_zip_b64
        sender._client.service.sendBill.return_value = mock_response

        result = sender.send_bill(FILENAME, XML_SAMPLE)

        sender._client.service.sendBill.assert_called_once()
        call_args = sender._client.service.sendBill.call_args.kwargs
        assert call_args["fileName"] == FILENAME + ".zip"
        assert zipfile.is_zipfile(io.BytesIO(call_args["contentFile"]))  # ZIP válido (bytes raw)
        assert result.code == "0"
        assert result.success is True

    def test_send_bill_soap_fault(self):
        sender = _make_sunat_sender()
        exc = Exception("SUNAT error")
        exc.faultcode = "EXP:0109"   # type: ignore[attr-defined]
        sender._client.service.sendBill.side_effect = exc

        result = sender.send_bill(FILENAME, XML_SAMPLE)

        assert result.success is False
        assert result.code == "0109"


class TestSunatSoapSenderSendSummary:
    def test_send_summary_retorna_ticket(self):
        sender = _make_sunat_sender()
        mock_response = MagicMock()
        mock_response.ticket = "1234567890"
        sender._client.service.sendSummary.return_value = mock_response

        result = sender.send_summary(FILENAME, XML_SAMPLE)

        assert result.success is True
        assert result.ticket == "1234567890"
        assert result.code == "0"

    def test_send_summary_sin_ticket(self):
        sender = _make_sunat_sender()
        mock_response = MagicMock()
        mock_response.ticket = None
        sender._client.service.sendSummary.return_value = mock_response

        result = sender.send_summary(FILENAME, XML_SAMPLE)

        assert result.success is False
        assert result.ticket is None


class TestSunatSoapSenderGetStatus:
    def test_get_status_completado(self, cdr_zip_b64):
        sender = _make_sunat_sender()
        mock_status = MagicMock()
        mock_status.statusCode = "0"
        mock_status.content = cdr_zip_b64
        mock_response = MagicMock()
        mock_response.status = mock_status
        sender._client.service.getStatus.return_value = mock_response

        result = sender.get_status("1234567890")

        assert result.success is True
        assert result.code == "0"

    def test_get_status_pendiente(self):
        sender = _make_sunat_sender()
        mock_status = MagicMock()
        mock_status.statusCode = "98"
        mock_status.content = None
        mock_response = MagicMock()
        mock_response.status = mock_status
        sender._client.service.getStatus.return_value = mock_response

        result = sender.get_status("1234567890")

        assert result.success is False
        assert result.code == "98"
        assert "pendiente" in result.description.lower()


# ---------------------------------------------------------------------------
# Tests: PseRestSender (httpx mockeado)
# ---------------------------------------------------------------------------

GIOR_BETA_ENDPOINTS = {
    "token":    "https://beta-pse.giortechnology.com/service/api/auth/cpe/token",
    "generate": "https://beta-pse.giortechnology.com/service/api/cpe/generar",
    "send":     "https://beta-pse.giortechnology.com/service/api/cpe/enviar",
    "query":    "https://beta-pse.giortechnology.com/service/api/cpe/consultar",
}


def _make_pse_sender(endpoints: dict | None = None) -> PseRestSender:
    with patch("app.services.sender._HTTPX_AVAILABLE", True):
        return PseRestSender(
            username="usertest",
            password="passtest",
            endpoints=endpoints or GIOR_BETA_ENDPOINTS,
        )


class TestPseRestSenderGetToken:
    def test_obtiene_token_y_cachea(self):
        sender = _make_pse_sender()
        mock_resp = MagicMock()
        mock_resp.status_code = 200
        mock_resp.json.return_value = {"access_token": "tok123"}
        mock_resp.raise_for_status = MagicMock()

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            token_1 = sender._get_token()
            token_2 = sender._get_token()

        assert token_1 == "tok123"
        assert token_2 == "tok123"
        # Solo debe llamar una vez (caché en segunda llamada)
        assert mock_httpx.post.call_count == 1

    def test_lanza_error_si_token_ausente(self):
        sender = _make_pse_sender()
        mock_resp = MagicMock()
        mock_resp.status_code = 200
        mock_resp.json.return_value = {"otro_campo": "valor"}
        mock_resp.raise_for_status = MagicMock()

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            with pytest.raises(RuntimeError, match="token"):
                sender._get_token()


class TestPseRestSenderSignXml:
    def test_sign_xml_exitoso(self):
        sender = _make_pse_sender()
        signed_b64 = base64.b64encode(b"<Signed/>").decode("ascii")

        mock_token_resp = MagicMock()
        mock_token_resp.status_code = 200
        mock_token_resp.json.return_value = {"access_token": "tok123"}
        mock_token_resp.raise_for_status = MagicMock()

        mock_sign_resp = MagicMock()
        mock_sign_resp.status_code = 200
        mock_sign_resp.json.return_value = {
            "xml": signed_b64,
            "hash_code": "ABCDEF1234",
            "message": "Firmado OK",
        }

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.side_effect = [mock_token_resp, mock_sign_resp]
            result = sender.sign_xml(FILENAME, XML_SAMPLE)

        assert result.success is True
        assert result.xml_signed_b64 == signed_b64
        assert result.hash_code == "ABCDEF1234"

    def test_sign_xml_error_http(self):
        sender = _make_pse_sender()
        sender._cached_token = "tok123"  # evitar llamada de token

        mock_resp = MagicMock()
        mock_resp.status_code = 401
        mock_resp.json.return_value = {"message": "No autorizado"}

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            result = sender.sign_xml(FILENAME, XML_SAMPLE)

        assert result.success is False
        assert result.code == "401"
        assert "autorizado" in result.description.lower()

    def test_sign_xml_sin_endpoint_generate(self):
        endpoints = {**GIOR_BETA_ENDPOINTS, "generate": ""}
        sender = _make_pse_sender(endpoints)
        sender._cached_token = "tok123"

        result = sender.sign_xml(FILENAME, XML_SAMPLE)

        assert result.success is False
        assert "generate" in result.description


# ---------------------------------------------------------------------------
# Tests: OseRestSender (httpx mockeado)
# ---------------------------------------------------------------------------

def _make_ose_sender(endpoints: dict | None = None) -> OseRestSender:
    with patch("app.services.sender._HTTPX_AVAILABLE", True):
        return OseRestSender(
            username="usertest",
            password="passtest",
            endpoints=endpoints or GIOR_BETA_ENDPOINTS,
        )


class TestOseRestSenderSendBill:
    def test_send_bill_exitoso(self, cdr_zip_b64):
        sender = _make_ose_sender()
        sender._cached_token = "tok123"

        mock_resp = MagicMock()
        mock_resp.status_code = 200
        mock_resp.json.return_value = {
            "estado": "0",
            "message": "Aceptado",
            "cdr": cdr_zip_b64,
        }

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            result = sender.send_bill(FILENAME, XML_SAMPLE)

        assert result.success is True
        assert result.cdr_b64 == cdr_zip_b64

    def test_send_bill_error_http(self):
        sender = _make_ose_sender()
        sender._cached_token = "tok123"

        mock_resp = MagicMock()
        mock_resp.status_code = 500
        mock_resp.json.return_value = {"errors": ["Error interno"]}

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            result = sender.send_bill(FILENAME, XML_SAMPLE)

        assert result.success is False
        assert result.code == "500"


class TestOseRestSenderSendSummary:
    def test_send_summary_retorna_ticket(self):
        sender = _make_ose_sender()
        sender._cached_token = "tok123"

        mock_resp = MagicMock()
        mock_resp.status_code = 202
        mock_resp.json.return_value = {
            "estado": "98",
            "ticket": "TKT-9999",
            "message": "En proceso",
        }

        with patch("app.services.sender.httpx") as mock_httpx:
            mock_httpx.post.return_value = mock_resp
            result = sender.send_summary(FILENAME, XML_SAMPLE)

        assert result.success is True
        assert result.ticket == "TKT-9999"

    def test_send_summary_sin_endpoint_send(self):
        endpoints = {**GIOR_BETA_ENDPOINTS, "send": ""}
        sender = _make_ose_sender(endpoints)
        sender._cached_token = "tok123"

        result = sender.send_summary(FILENAME, XML_SAMPLE)
        assert result.success is False
        assert "send" in result.description


# ---------------------------------------------------------------------------
# Tests: funciones de fábrica
# ---------------------------------------------------------------------------

class TestBuildSunatSender:
    def test_crea_instancia_en_demo(self, company_config_sunat):
        with patch("app.services.sender._ZEEP_AVAILABLE", True), \
             patch("app.services.sender.ZeepClient"), \
             patch("app.services.sender.Transport"), \
             patch("app.services.sender.ZeepSettings"), \
             patch("app.services.sender.UsernameToken"):
            sender = build_sunat_sender("invoice", company_config_sunat)
        assert isinstance(sender, SunatSoapSender)

    def test_crea_instancia_en_prod(self, company_config_sunat):
        config = {**company_config_sunat, "sunat_mode": "prod"}
        with patch("app.services.sender._ZEEP_AVAILABLE", True), \
             patch("app.services.sender.ZeepClient") as MockClient, \
             patch("app.services.sender.Transport"), \
             patch("app.services.sender.ZeepSettings"), \
             patch("app.services.sender.UsernameToken"):
            build_sunat_sender("invoice", config)
            wsdl_arg = MockClient.call_args.args[0] if MockClient.call_args.args else MockClient.call_args.kwargs.get("wsdl")
            assert wsdl_arg is None or "beta" not in str(wsdl_arg)

    def test_falla_sin_zeep(self, company_config_sunat):
        with patch("app.services.sender._ZEEP_AVAILABLE", False):
            with pytest.raises(RuntimeError, match="zeep"):
                build_sunat_sender("invoice", company_config_sunat)


class TestBuildSunatCdrSender:
    def test_crea_instancia(self, company_config_sunat):
        with patch("app.services.sender._ZEEP_AVAILABLE", True), \
             patch("app.services.sender.ZeepClient"), \
             patch("app.services.sender.Transport"), \
             patch("app.services.sender.ZeepSettings"), \
             patch("app.services.sender.UsernameToken"):
            sender = build_sunat_cdr_sender(company_config_sunat)
        assert isinstance(sender, SunatSoapSender)


class TestBuildPseSender:
    def test_crea_instancia_gior(self, company_config_pse):
        with patch("app.services.sender._HTTPX_AVAILABLE", True):
            sender = build_pse_sender(company_config_pse)
        assert isinstance(sender, PseRestSender)
        assert "beta-pse.giortechnology" in sender._endpoints["token"]

    def test_crea_instancia_custom(self, company_config_custom):
        with patch("app.services.sender._HTTPX_AVAILABLE", True):
            sender = build_pse_sender(company_config_custom)
        assert isinstance(sender, PseRestSender)
        assert sender._endpoints["token"] == "https://custom.example.com/token"

    def test_falla_sin_httpx(self, company_config_pse):
        with patch("app.services.sender._HTTPX_AVAILABLE", False):
            with pytest.raises(RuntimeError, match="httpx"):
                build_pse_sender(company_config_pse)


class TestBuildOseSender:
    def test_crea_instancia(self, company_config_pse):
        with patch("app.services.sender._HTTPX_AVAILABLE", True):
            sender = build_ose_sender(company_config_pse)
        assert isinstance(sender, OseRestSender)
        # OSE usa soap_username (credenciales SOL), no pse_username
        assert sender._username == "MODDATOS"

    def test_credentials_son_soap(self, company_config_pse):
        """Verificar que OSE use soap_username/soap_password, no pse_username/pse_password."""
        with patch("app.services.sender._HTTPX_AVAILABLE", True):
            sender = build_ose_sender(company_config_pse)
        assert sender._username == company_config_pse["soap_username"]
        assert sender._password == company_config_pse["soap_password"]
