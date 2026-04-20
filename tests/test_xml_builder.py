"""
Tests del generador de XML (xml_builder).

Verifica que los templates Jinja2 generen XML válido
con la estructura correcta para ser aceptado por SUNAT.
"""
import pytest
from lxml import etree

from app.services.xml_builder import build_xml, _clean_xml


# Namespaces UBL 2.1
_NS = {
    "cbc": "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2",
    "cac": "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2",
    "ext": "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2",
}


@pytest.fixture
def invoice_context():
    """
    Contexto mínimo para renderizar la plantilla de factura.
    Estructura plana — los totales van directamente en document.*
    (igual que el contexto que produce _build_template_context del pipeline).
    """
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
            # Totales planos (aplanados desde totals por _build_template_context)
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
            # Campos opcionales necesarios para lógica condicional
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


class TestXmlBuilder:
    def test_genera_xml_factura(self, invoice_context):
        xml_str = build_xml("invoice", invoice_context)
        assert xml_str.strip().startswith("<?xml")
        assert "Invoice" in xml_str
        assert "F001-1" in xml_str

    def test_xml_parseable(self, invoice_context):
        xml_str = build_xml("invoice", invoice_context)
        # Debe ser XML válido (lxml no debe lanzar excepción)
        root = etree.fromstring(xml_str.encode("utf-8"))
        assert root is not None

    def test_ruc_emisor_en_xml(self, invoice_context):
        xml_str = build_xml("invoice", invoice_context)
        root = etree.fromstring(xml_str.encode("utf-8"))
        # Verificar RUC del emisor en AccountingSupplierParty (nuevo path UBL 2.1 PHP-fiel)
        supplier_id = root.find(
            ".//cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID",
            _NS,
        )
        assert supplier_id is not None, "No se encontró ID del proveedor en el XML"
        assert supplier_id.text == "20600000001"

    def test_serie_numero_en_id(self, invoice_context):
        xml_str = build_xml("invoice", invoice_context)
        root = etree.fromstring(xml_str.encode("utf-8"))
        id_node = root.find("cbc:ID", _NS)
        assert id_node is not None
        assert id_node.text == "F001-1"

    def test_total_en_xml(self, invoice_context):
        xml_str = build_xml("invoice", invoice_context)
        root = etree.fromstring(xml_str.encode("utf-8"))
        payable = root.find(".//cac:LegalMonetaryTotal/cbc:PayableAmount", _NS)
        assert payable is not None
        assert payable.text == "236.00"

    def test_extension_content_vacio_presente(self, invoice_context):
        """SUNAT requiere ext:ExtensionContent vacío para insertar la firma."""
        xml_str = build_xml("invoice", invoice_context)
        root = etree.fromstring(xml_str.encode("utf-8"))
        extensions = root.findall(".//ext:ExtensionContent", _NS)
        # Debe haber al menos uno vacío para la firma
        empty = [e for e in extensions if len(e) == 0 and not (e.text or "").strip()]
        assert len(empty) >= 1

    def test_tipo_invalido_lanza_error(self, invoice_context):
        with pytest.raises(ValueError, match="plantilla"):
            build_xml("tipo_inexistente", invoice_context)

    def test_clean_xml_elimina_lineas_vacias(self):
        sucio = "<?xml version='1.0'?>\n\n\n<root/>\n\n\n"
        limpio = _clean_xml(sucio)
        assert "\n\n\n" not in limpio
