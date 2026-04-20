"""
Tests de los schemas Pydantic.

Verifica que la validación de entrada funciona correctamente
antes de que el pipeline se ejecute.
"""
import pytest
from decimal import Decimal

from app.schemas.document import DocumentRequest, DocumentType, CurrencyType
from app.schemas.company import CompanyRegisterRequest, SunatMode, SoapSendType


# ---------------------------------------------------------------------------
# Fixtures compartidos
# ---------------------------------------------------------------------------

@pytest.fixture
def valid_company_payload():
    return {
        "legal_number": "20600000001",
        "legal_name": "Empresa Demo S.A.C.",
        "sunat_mode": "demo",
        "soap_send_type": "sunat",
        "soap_username": "20600000001DEMO",
        "soap_password": "clave_demo",
        "certificate_b64": "dGVzdA==",  # base64 de "test"
    }


@pytest.fixture
def valid_invoice_payload():
    return {
        "company_ruc": "20600000001",
        "type": "invoice",
        "document_type_id": "01",
        "series": "F001",
        "number": "1",
        "date_of_issue": "2026-04-01",
        "establishment": {
            "address": "Av. Demo 123",
            "district": "Lima",
            "province": "Lima",
            "department": "Lima",
            "ubigeo": "150101",
            "country_id": "PE",
        },
        "customer": {
            "identity_document_type_id": "6",
            "number": "20100000001",
            "name": "Cliente Demo S.A.C.",
            "address": "Av. Cliente 456",
        },
        "currency_type_id": "PEN",
        "items": [
            {
                "description": "Producto de prueba",
                "unit_type_id": "NIU",
                "quantity": "2",
                "unit_value": "100.00",
                "unit_price": "118.00",
                "price_type_id": "01",
                "affectation_igv_type_id": "10",
                "total_base_igv": "200.00",
                "percentage_igv": "18.00",
                "total_igv": "36.00",
                "total_taxes": "36.00",
                "total_value": "200.00",
                "total": "236.00",
            }
        ],
        "totals": {
            "total_taxed": "200.00",
            "total_igv": "36.00",
            "total_taxes": "36.00",
            "total_value": "200.00",
            "total": "236.00",
        },
        "actions": {
            "send_xml_signed": False,
            "send_email": False,
        },
    }


# ---------------------------------------------------------------------------
# Tests: schema Company
# ---------------------------------------------------------------------------

class TestCompanySchema:
    def test_company_registro_valido(self, valid_company_payload):
        company = CompanyRegisterRequest(**valid_company_payload)
        assert company.legal_number == "20600000001"
        assert company.sunat_mode == SunatMode.demo
        assert company.soap_send_type == SoapSendType.sunat

    def test_ruc_invalido_letras(self, valid_company_payload):
        valid_company_payload["legal_number"] = "2060000000A"
        with pytest.raises(Exception):
            CompanyRegisterRequest(**valid_company_payload)

    def test_ruc_invalido_longitud(self, valid_company_payload):
        valid_company_payload["legal_number"] = "206000"
        with pytest.raises(Exception):
            CompanyRegisterRequest(**valid_company_payload)

    def test_certificate_b64_invalido(self, valid_company_payload):
        """El schema acepta cualquier string; la validación real la hace company_store."""
        valid_company_payload["certificate_b64"] = "no-es-base64!!!"
        # El schema lo acepta; el store lo rechazará al decodificar
        company = CompanyRegisterRequest(**valid_company_payload)
        assert company.certificate_b64 == "no-es-base64!!!"


# ---------------------------------------------------------------------------
# Tests: schema Document
# ---------------------------------------------------------------------------

class TestDocumentSchema:
    def test_factura_valida(self, valid_invoice_payload):
        doc = DocumentRequest(**valid_invoice_payload)
        assert doc.type == DocumentType.invoice
        assert doc.series == "F001"
        assert doc.totals.total == Decimal("236.00")

    def test_factura_sin_customer_falla(self, valid_invoice_payload):
        valid_invoice_payload.pop("customer")
        with pytest.raises(Exception, match="customer"):
            DocumentRequest(**valid_invoice_payload)

    def test_factura_sin_items_falla(self, valid_invoice_payload):
        valid_invoice_payload.pop("items")
        with pytest.raises(Exception, match="items"):
            DocumentRequest(**valid_invoice_payload)

    def test_factura_sin_totales_falla(self, valid_invoice_payload):
        valid_invoice_payload.pop("totals")
        with pytest.raises(Exception, match="totals"):
            DocumentRequest(**valid_invoice_payload)

    def test_ruc_emisor_invalido(self, valid_invoice_payload):
        valid_invoice_payload["company_ruc"] = "123"
        with pytest.raises(Exception):
            DocumentRequest(**valid_invoice_payload)

    def test_filename_autogenerado_si_omitido(self, valid_invoice_payload):
        doc = DocumentRequest(**valid_invoice_payload)
        assert doc.filename is None  # Se genera en pipeline._build_filename

    def test_filename_custom(self, valid_invoice_payload):
        valid_invoice_payload["filename"] = "20600000001-01-F001-1"
        doc = DocumentRequest(**valid_invoice_payload)
        assert doc.filename == "20600000001-01-F001-1"
