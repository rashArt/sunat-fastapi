"""
Tests de firma XML digital (Fase 3).

Verifica que XmlSigner firme correctamente un documento UBL según el
algoritmo requerido por SUNAT:
  - Canonicalización C14N (inclusive, no exclusiva)
  - Firma RSA-SHA1 con clave del certificado .pem combinado
  - Digest SHA1 en la referencia URI=""
  - Inserción del bloque ds:Signature dentro del ext:ExtensionContent vacío
  - Atributo Id en <ds:Signature> igual al valor de <cac:Signature><cbc:ID>
  - Bloque X509Certificate en ds:KeyInfo

El test usa el certificado de demostración real ubicado en:
  storage/20600000001/certificate/certificate.pem
  (copiado desde corefacturalo/CoreFacturalo/WS/Signed/Resources/certificate.pem)
"""
import pytest
from pathlib import Path
from lxml import etree

from app.services.signer import XmlSigner
from app.services.xml_builder import build_xml

# ------------------------------------------------------------------
# Constantes
# ------------------------------------------------------------------

# Ruta al certificado de prueba (combinado cert + clave privada)
_CERT_PATH = Path(__file__).parent.parent / "storage" / "20600000001" / "certificate" / "certificate.pem"

# Namespaces
_NS = {
    "ds":  "http://www.w3.org/2000/09/xmldsig#",
    "ext": "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2",
    "cac": "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2",
    "cbc": "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2",
}


# ------------------------------------------------------------------
# Fixtures
# ------------------------------------------------------------------

@pytest.fixture(scope="module")
def signer():
    """XmlSigner con el certificado de demostración cargado."""
    s = XmlSigner()
    s.load_combined_pem(_CERT_PATH)
    return s


@pytest.fixture(scope="module")
def invoice_context():
    """Contexto mínimo de factura para generar un XML UBL válido antes de firmar."""
    return {
        "ubl_version": "2.1",
        "customization_id": "2.0",
        "company": {
            "legal_number": "20600000001",
            "legal_name": "Empresa Demo S.A.C.",
            "trade_name": "Empresa Demo",
            "signature_uri": "IDFirma",
            "signature_note": "Empresa Demo S.A.C.",
        },
        "document": {
            "type": "invoice",
            "document_type_id": "01",
            "series": "F001",
            "number": "1",
            "date_of_issue": "2026-04-01",
            "time_of_issue": "12:00:00",
            "currency_type_id": "PEN",
            "exchange_rate_sale": "1.00",
            "operation_type_id": "0101",
            "filename": None,
            "related": [],
            "guides": [],
            "legends": [{"code": "1000", "value": "SON DOSCIENTOS TREINTA Y SEIS CON 00/100 SOLES"}],
            "establishment": {
                "address": "Av. Demo 123",
                "district": "Lima",
                "province": "Lima",
                "department": "Lima",
                "district_id": "150101",
                "code": "0000",
                "urbanization": None,
                "country_id": "PE",
                "email": None,
                "telephone": None,
            },
            "customer": {
                "identity_document_type_id": "6",
                "number": "20100000001",
                "name": "Cliente Demo S.A.C.",
                "address": "Av. Cliente 456",
                "district_id": "150101",
                "country_id": "PE",
                "email": None,
                "telephone": None,
            },
            "total_exportation": "0.00",
            "total_taxed": "200.00",
            "total_unaffected": "0.00",
            "total_exonerated": "0.00",
            "total_free": "0.00",
            "total_igv": "36.00",
            "total_base_isc": "0.00",
            "total_isc": "0.00",
            "total_base_other_taxes": "0.00",
            "total_other_taxes": "0.00",
            "total_plastic_bag_taxes": "0.00",
            "total_taxes": "36.00",
            "total_value": "200.00",
            "total_discount": "0.00",
            "total_charge": "0.00",
            "total_prepayment": "0.00",
            "subtotal": "236.00",
            "total": "236.00",
            "tot_charges": "0.00",
            "total_discount_no_base": "0.00",
            "fee_total": "0.00",
            "payment_condition_id": "01",
            "detraction": None,
            "prepayments": [],
            "charges": [],
            "discounts": [],
            "fee": [],
            "invoice": {"operation_type_id": "0101", "date_of_due": None},
            "items": [
                {
                    "description": "Producto de prueba",
                    "name_product_xml": None,
                    "internal_id": "PROD-001",
                    "item_code": None,
                    "item_code_gs1": None,
                    "unit_type_id": "NIU",
                    "quantity": "2",
                    "unit_value": "100.000000",
                    "unit_price": "118.000000",
                    "price_type_id": "01",
                    "affectation_igv_type_id": "10",
                    "total_base_igv": "200.00",
                    "percentage_igv": "18.00",
                    "total_igv": "36.00",
                    "total_base_isc": "0.00",
                    "percentage_isc": "0.00",
                    "system_isc_type_id": None,
                    "total_isc": "0.00",
                    "total_base_other_taxes": "0.00",
                    "percentage_other_taxes": "0.00",
                    "total_other_taxes": "0.00",
                    "total_plastic_bag_taxes": "0.00",
                    "amount_plastic_bag_taxes": "0.00",
                    "total_taxes": "36.00",
                    "total_value": "200.00",
                    "total_charge": "0.00",
                    "total_discount": "0.00",
                    "total": "236.00",
                    "discounts": [],
                    "charges": [],
                    "attributes": [],
                    "additional_information": None,
                }
            ],
        },
    }


@pytest.fixture(scope="module")
def signed_xml_bytes(signer, invoice_context):
    """Genera y firma un XML de factura. Reutilizado en todos los tests de estructura."""
    unsigned = build_xml("invoice", invoice_context)
    return signer.sign(unsigned)


# ------------------------------------------------------------------
# Tests
# ------------------------------------------------------------------

class TestLoadCombinedPem:
    """Verifica que el certificado combinado se cargue correctamente."""

    def test_cert_path_exists(self):
        assert _CERT_PATH.exists(), f"Certificado no encontrado en {_CERT_PATH}"

    def test_signer_loaded(self, signer):
        # Si load_combined_pem lanzó excepción, el fixture ya habría fallado.
        assert signer is not None
        assert signer._private_key is not None
        assert signer._cert_pem is not None

    def test_cert_pem_contains_certificate_block(self, signer):
        assert b"BEGIN CERTIFICATE" in signer._cert_pem


class TestSignedXmlStructure:
    """Verifica la estructura del XML firmado."""

    def test_sign_returns_bytes(self, signed_xml_bytes):
        assert isinstance(signed_xml_bytes, bytes)
        assert len(signed_xml_bytes) > 0

    def test_signed_xml_is_well_formed(self, signed_xml_bytes):
        """El XML firmado debe ser XML válido parseable por lxml."""
        root = etree.fromstring(signed_xml_bytes)
        assert root is not None

    def test_signature_inside_extension_content(self, signed_xml_bytes):
        """
        El nodo ds:Signature debe estar dentro de ext:ExtensionContent.
        Equivalente al comportamiento de getNodeSign() en XmlSigned.php.
        """
        root = etree.fromstring(signed_xml_bytes)
        ext_contents = root.findall(".//ext:ExtensionContent", _NS)
        assert len(ext_contents) > 0, "No se encontró ext:ExtensionContent en el XML"

        # El primer ExtensionContent debe contener el ds:Signature
        first_ext = ext_contents[0]
        signature = first_ext.find("ds:Signature", _NS)
        assert signature is not None, "ds:Signature no está dentro de ext:ExtensionContent"

    def test_signature_id_attribute(self, signed_xml_bytes):
        """
        El atributo Id de ds:Signature debe coincidir con el valor de
        <cac:Signature><cbc:ID> del documento UBL (ej. 'IDFirma').
        """
        root = etree.fromstring(signed_xml_bytes)
        # Leer el ID declarado en el UBL
        ubl_sig_id = None
        for sig_el in root.findall(f".//{{{_NS['cac']}}}Signature"):
            id_el = sig_el.find(f"{{{_NS['cbc']}}}ID")
            if id_el is not None and id_el.text:
                ubl_sig_id = id_el.text.strip()
                break

        assert ubl_sig_id is not None, "<cac:Signature><cbc:ID> no encontrado en el UBL"

        # Verificar atributo Id en ds:Signature
        ds_signature = root.find(".//ds:Signature", _NS)
        assert ds_signature is not None
        assert ds_signature.get("Id") == ubl_sig_id, (
            f"Id en ds:Signature ('{ds_signature.get('Id')}') "
            f"no coincide con el UBL ('{ubl_sig_id}')"
        )

    def test_signature_value_non_empty(self, signed_xml_bytes):
        """ds:SignatureValue debe tener contenido base64 (la firma RSA-SHA1)."""
        root = etree.fromstring(signed_xml_bytes)
        sig_value = root.find(".//ds:SignatureValue", _NS)
        assert sig_value is not None, "ds:SignatureValue no encontrado"
        assert sig_value.text is not None and len(sig_value.text.strip()) > 0, \
            "ds:SignatureValue está vacío"

    def test_digest_value_non_empty(self, signed_xml_bytes):
        """ds:DigestValue debe contener el hash SHA1 del documento."""
        root = etree.fromstring(signed_xml_bytes)
        digest_value = root.find(".//ds:DigestValue", _NS)
        assert digest_value is not None, "ds:DigestValue no encontrado"
        assert digest_value.text is not None and len(digest_value.text.strip()) > 0

    def test_reference_uri_empty_string(self, signed_xml_bytes):
        """
        La referencia debe tener URI="" que apunta al documento entero.
        Equivalente a force_uri => true en XmlSigned.php.
        """
        root = etree.fromstring(signed_xml_bytes)
        reference = root.find(".//ds:Reference", _NS)
        assert reference is not None, "ds:Reference no encontrado"
        assert reference.get("URI") == "", \
            f"URI de referencia debe ser '' pero es '{reference.get('URI')}'"

    def test_enveloped_transform_present(self, signed_xml_bytes):
        """La única transformación en la referencia es el enveloped-signature."""
        root = etree.fromstring(signed_xml_bytes)
        transforms = root.findall(".//ds:Transform", _NS)
        algs = [t.get("Algorithm") for t in transforms]
        assert "http://www.w3.org/2000/09/xmldsig#enveloped-signature" in algs

    def test_x509_certificate_present(self, signed_xml_bytes):
        """
        El bloque ds:X509Certificate debe estar presente en ds:KeyInfo.
        Equivalente a add509Cert() en XmlSigned.php.
        """
        root = etree.fromstring(signed_xml_bytes)
        x509_cert = root.find(".//ds:X509Certificate", _NS)
        assert x509_cert is not None, "ds:X509Certificate no encontrado en KeyInfo"
        assert x509_cert.text is not None and len(x509_cert.text.strip()) > 0, \
            "ds:X509Certificate está vacío"

    def test_canonicalization_method_c14n(self, signed_xml_bytes):
        """
        El método de canonicalización debe ser C14N regular (no exclusivo).
        Equivalente a XMLSecurityDSig::C14N en PHP.
        """
        root = etree.fromstring(signed_xml_bytes)
        c14n = root.find(".//ds:CanonicalizationMethod", _NS)
        assert c14n is not None
        assert c14n.get("Algorithm") == "http://www.w3.org/TR/2001/REC-xml-c14n-20010315", \
            f"Se esperaba C14N regular, se obtuvo: {c14n.get('Algorithm')}"

    def test_signature_method_rsa_sha1(self, signed_xml_bytes):
        """El algoritmo de firma debe ser RSA-SHA1 (requerido por SUNAT)."""
        root = etree.fromstring(signed_xml_bytes)
        sig_method = root.find(".//ds:SignatureMethod", _NS)
        assert sig_method is not None
        assert sig_method.get("Algorithm") == "http://www.w3.org/2000/09/xmldsig#rsa-sha1"

    def test_digest_method_sha1(self, signed_xml_bytes):
        """El algoritmo de digest de la referencia debe ser SHA1."""
        root = etree.fromstring(signed_xml_bytes)
        digest_method = root.find(".//ds:DigestMethod", _NS)
        assert digest_method is not None
        assert digest_method.get("Algorithm") == "http://www.w3.org/2000/09/xmldsig#sha1"


class TestSignatureVerification:
    """Verifica criptográficamente la firma usando la misma clave."""

    def test_verify_returns_true_for_valid_signature(self, signer, signed_xml_bytes):
        """
        signer.verify() debe retornar True para un XML firmado con el mismo certificado.
        Valida que la firma RSA-SHA1 es criptográficamente correcta.
        """
        result = signer.verify(signed_xml_bytes)
        assert result is True, "La verificación de la firma devolvió False — firma inválida"

    def test_verify_returns_false_for_tampered_xml(self, signer, signed_xml_bytes):
        """
        Si se modifica el XML después de firmarlo, verify() debe retornar False.
        Garantiza integridad del documento.
        """
        # Modificar el XML firmado alterando un valor
        tampered = signed_xml_bytes.replace(b"Empresa Demo S.A.C.", b"Empresa Falsa S.A.C.")
        result = signer.verify(tampered)
        assert result is False, "La verificación debería fallar para un XML alterado"
