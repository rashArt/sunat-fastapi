"""
Tests de los endpoints FastAPI usando TestClient.

Verifica el comportamiento HTTP de /api/v1/companies y /api/v1/documents.
"""
import base64
from typing import Generator
from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from app.core.db import Base, get_db
from app.main import app

# Base de datos SQLite en memoria para tests (sin dependencia de PostgreSQL)
TEST_DATABASE_URL = "sqlite:///:memory:"
_test_engine = create_engine(
    TEST_DATABASE_URL,
    connect_args={"check_same_thread": False},
)
_TestingSession = sessionmaker(bind=_test_engine, autoflush=False, autocommit=False)

client = TestClient(app)


# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------

@pytest.fixture(autouse=True)
def setup_test_db(tmp_path, monkeypatch):
    """
    Prepara una BD SQLite en memoria para cada test:
      - Crea todas las tablas definidas en los modelos ORM.
      - Sobreescribe la dependencia get_db del app con la sesión de test.
      - Redirige el storage al directorio temporal para aislar artefactos.
      - Desactiva la validación del certificado para no necesitar .p12/.pem reales.
    """
    from app.core import config

    Base.metadata.create_all(bind=_test_engine)

    def override_get_db() -> Generator[Session, None, None]:
        db = _TestingSession()
        try:
            yield db
        finally:
            db.close()

    app.dependency_overrides[get_db] = override_get_db
    monkeypatch.setattr(config.settings, "STORAGE_LOCAL_PATH", str(tmp_path))

    yield

    Base.metadata.drop_all(bind=_test_engine)
    app.dependency_overrides.clear()


def _cert_b64() -> str:
    """Retorna un certificado .p12 ficticio en base64 (para tests unitarios)."""
    return base64.b64encode(b"fake_certificate_content").decode()


def _mock_save_cert(legal_number: str, payload) -> tuple[str, str]:
    """Mock de _save_and_validate_certificate: omite validación real del cert."""
    return "p12", f"/tmp/{legal_number}/certificate/certificate.p12"


@pytest.fixture
def registered_company(tmp_path):
    """Registra una compañía demo de prueba con certificado mockeado."""
    payload = {
        "legal_number": "20600000001",
        "legal_name": "Empresa Demo S.A.C.",
        "sunat_mode": "demo",
        "soap_send_type": "sunat",
        "soap_username": "20600000001DEMO",
        "soap_password": "clave_demo",
        "certificate_b64": _cert_b64(),
    }
    with patch("app.services.company_store._save_and_validate_certificate", side_effect=_mock_save_cert):
        response = client.post("/api/v1/companies", json=payload)
    assert response.status_code == 201
    return payload


# ---------------------------------------------------------------------------
# Tests: /api/v1/companies
# ---------------------------------------------------------------------------

class TestCompaniesEndpoint:
    def test_register_company_successful(self):
        payload = {
            "legal_number": "20600000001",
            "legal_name": "Empresa Demo S.A.C.",
            "sunat_mode": "demo",
            "soap_send_type": "sunat",
            "soap_username": "20600000001DEMO",
            "soap_password": "clave_demo",
            "certificate_b64": _cert_b64(),
        }
        with patch("app.services.company_store._save_and_validate_certificate", side_effect=_mock_save_cert):
            response = client.post("/api/v1/companies", json=payload)
        assert response.status_code == 201
        data = response.json()
        assert data["legal_number"] == "20600000001"
        assert data["sunat_mode"] == "demo"

    def test_register_company_invalid_ruc(self):
        payload = {
            "legal_number": "123",  # inválido
            "legal_name": "Test",
            "soap_username": "user",
            "soap_password": "pass",
            "certificate_b64": _cert_b64(),
        }
        response = client.post("/api/v1/companies", json=payload)
        assert response.status_code == 422

    def test_register_company_without_certificate(self):
        payload = {
            "legal_number": "20600000001",
            "legal_name": "Test",
            "soap_username": "user",
            "soap_password": "pass",
            # sin certificate_b64
        }
        response = client.post("/api/v1/companies", json=payload)
        assert response.status_code == 422

    def test_get_company_returns_full_data(self, registered_company):
        # Obtener datos completos por RUC
        response = client.get(f"/api/v1/companies/{registered_company['legal_number']}")
        assert response.status_code == 200
        data = response.json()
        assert data["legal_number"] == registered_company["legal_number"]
        # Los campos agregados deben aparecer con valores por defecto
        assert data["response_mode"] == "hybrid"
        assert data["wait_seconds"] == 10
        assert data["status"] == "active"

    def test_register_company_with_operational_fields(self):
        payload = {
            "legal_number": "20600000002",
            "legal_name": "Empresa Test S.A.C.",
            "sunat_mode": "demo",
            "soap_send_type": "sunat",
            "soap_username": "user",
            "soap_password": "pass",
            "certificate_b64": _cert_b64(),
            "response_mode": "async",
            "wait_seconds": 5,
            "auto_poll_ticket": True,
            "ticket_wait_seconds": 30,
            "ticket_poll_interval_ms": 500,
            "api_token": "tok-123",
            "status": "active",
        }
        with patch("app.services.company_store._save_and_validate_certificate", side_effect=_mock_save_cert):
            response = client.post("/api/v1/companies", json=payload)
        assert response.status_code == 201
        data = response.json()
        assert data["legal_number"] == "20600000002"
        assert data["response_mode"] == "async"
        assert data["wait_seconds"] == 5


# ---------------------------------------------------------------------------
# Tests: /api/v1/documents
# ---------------------------------------------------------------------------

class TestDocumentsEndpoint:
    def _invoice_payload(self):
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
            },
        }

    def test_document_without_registered_company_returns_404(self):
        response = client.post("/api/v1/documents", json=self._invoice_payload())
        assert response.status_code == 404
        assert "no encontrada" in response.json()["detail"].lower()

    def test_document_with_company_generates_xml(self, registered_company):
        """Con send_xml_signed=False el pipeline genera XML sin intentar enviar a SUNAT."""
        response = client.post("/api/v1/documents", json=self._invoice_payload())
        assert response.status_code == 200
        data = response.json()
        assert data["filename"] == "20600000001-01-F001-1"
        # El XML sin firma debe estar en base64
        assert data["xml_unsigned_b64"] is not None
        xml_bytes = base64.b64decode(data["xml_unsigned_b64"])
        assert b"<Invoice" in xml_bytes

    def test_document_invalid_payload_returns_422(self, registered_company):
        payload = self._invoice_payload()
        payload.pop("items")
        response = client.post("/api/v1/documents", json=payload)
        assert response.status_code == 422

    def test_health_check(self):
        response = client.get("/health")
        assert response.status_code == 200
        assert response.json()["status"] == "ok"
