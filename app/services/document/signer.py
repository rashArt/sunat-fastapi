"""
Firmador digital XML para SUNAT.

Implementa la firma enveloped (XML Digital Signature) con:
  - Canonicalización C14N (Canonical XML 1.0)
  - Algoritmo de firma RSA-SHA1 (compatible con SUNAT Perú)
  - Inserción de la firma en el nodo ext:ExtensionContent vacío
  - Inclusión del certificado X509 en el bloque KeyInfo

Equivalente a XmlSigned.php en CoreFacturalo.

NOTA: python-xmlsec requiere libxmlsec1 instalado en el sistema.
En Windows instalar el wheel precompilado:
  pip install xmlsec
"""
from __future__ import annotations

import base64
import hashlib
import logging
from pathlib import Path

logger = logging.getLogger(__name__)

try:
    import xmlsec
    from lxml import etree
    _XMLSEC_AVAILABLE = True
except ImportError:
    _XMLSEC_AVAILABLE = False
    logger.warning(
        "python-xmlsec o lxml no disponible. La firma XML no funcionará hasta instalar las dependencias."
    )

try:
    from cryptography.hazmat.primitives.serialization import pkcs12
    from cryptography.hazmat.backends import default_backend
    _CRYPTO_AVAILABLE = True
except ImportError:
    _CRYPTO_AVAILABLE = False


# Namespaces UBL / SUNAT usados en la inserción de la firma
_NS_EXT = "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
_NS_DS = "http://www.w3.org/2000/09/xmldsig#"


def _split_combined_pem(pem_content: bytes) -> tuple[bytes, bytes]:
    """
    Divide un PEM combinado (un archivo con CERTIFICATE y PRIVATE KEY) en dos partes.

    Retorna (cert_pem, key_pem) como bytes independientes.
    Soporta 'BEGIN PRIVATE KEY' (PKCS#8) y 'BEGIN RSA PRIVATE KEY' (PKCS#1).
    """
    text = pem_content.decode("ascii", errors="replace")

    # Extraer bloque del certificado
    cert_start = text.find("-----BEGIN CERTIFICATE-----")
    cert_end = text.find("-----END CERTIFICATE-----") + len("-----END CERTIFICATE-----")
    cert_pem = text[cert_start:cert_end].strip().encode("ascii") + b"\n"

    # Extraer bloque de la clave privada (PKCS#8 o PKCS#1)
    for begin_marker in ("-----BEGIN PRIVATE KEY-----", "-----BEGIN RSA PRIVATE KEY-----"):
        key_start = text.find(begin_marker)
        if key_start != -1:
            end_marker = begin_marker.replace("BEGIN", "END")
            key_end = text.find(end_marker) + len(end_marker)
            key_pem = text[key_start:key_end].strip().encode("ascii") + b"\n"
            return cert_pem, key_pem

    raise ValueError("No se encontró bloque de clave privada en el PEM.")


class XmlSigner:
    """
    Firma XML utilizando un certificado .p12 o .pem.

    Uso:
        signer = XmlSigner()
        signer.load_p12(cert_path, password)
        signed_xml = signer.sign(unsigned_xml_str)
    """

    def __init__(self) -> None:
        self._private_key = None   # xmlsec key object
        self._cert_pem: bytes | None = None

    # ------------------------------------------------------------------
    # Carga de certificados
    # ------------------------------------------------------------------

    def load_p12(self, p12_path: str | Path, password: str | None = None) -> None:
        """
        Carga un certificado PKCS#12 (.p12) y extrae clave privada + certificado.

        Args:
            p12_path: Ruta al archivo .p12
            password: Contraseña del .p12 (None si no tiene)
        """
        if not _XMLSEC_AVAILABLE or not _CRYPTO_AVAILABLE:
            raise RuntimeError("python-xmlsec y cryptography son necesarios para la firma XML.")

        p12_bytes = Path(p12_path).read_bytes()
        pwd_bytes = password.encode() if password else None

        # Parsear PKCS12 con cryptography para extraer PEM
        private_key, cert, _ = pkcs12.load_key_and_certificates(
            p12_bytes, pwd_bytes, backend=default_backend()
        )

        # Serializar a PEM
        from cryptography.hazmat.primitives.serialization import (
            Encoding, PrivateFormat, NoEncryption
        )
        from cryptography.x509 import Certificate
        key_pem = private_key.private_bytes(Encoding.PEM, PrivateFormat.TraditionalOpenSSL, NoEncryption())
        cert_pem = cert.public_bytes(Encoding.PEM)

        self._cert_pem = cert_pem
        self._private_key = xmlsec.Key.from_memory(key_pem, xmlsec.constants.KeyDataFormatPem)
        self._private_key.load_cert_from_memory(cert_pem, xmlsec.constants.KeyDataFormatCertPem)
        logger.debug("Certificado .p12 cargado correctamente desde %s", p12_path)

    def load_pem(self, key_pem_path: str | Path, cert_pem_path: str | Path) -> None:
        """
        Carga clave privada y certificado en formato PEM por separado.
        """
        if not _XMLSEC_AVAILABLE:
            raise RuntimeError("python-xmlsec es necesario para la firma XML.")

        self._private_key = xmlsec.Key.from_file(
            str(key_pem_path), xmlsec.constants.KeyDataFormatPem
        )
        self._private_key.load_cert_from_file(
            str(cert_pem_path), xmlsec.constants.KeyDataFormatCertPem
        )
        self._cert_pem = Path(cert_pem_path).read_bytes()
        logger.debug("Clave PEM cargada desde %s", key_pem_path)

    def load_combined_pem(self, pem_path: str | Path) -> None:
        """
        Carga un PEM combinado que contiene tanto el certificado como la clave
        privada en el mismo archivo (formato usado por CoreFacturalo/SUNAT).

        Equivalente a XmlSigned::setCertificate($pem) en PHP, donde el mismo
        string se usa como privateKey y publicKey.

        Args:
            pem_path: Ruta al archivo .pem combinado.
        """
        if not _XMLSEC_AVAILABLE:
            raise RuntimeError("python-xmlsec es necesario para la firma XML.")

        cert_pem, key_pem = _split_combined_pem(Path(pem_path).read_bytes())
        self._cert_pem = cert_pem
        self._private_key = xmlsec.Key.from_memory(key_pem, xmlsec.constants.KeyDataFormatPem)
        self._private_key.load_cert_from_memory(cert_pem, xmlsec.constants.KeyDataFormatCertPem)
        logger.debug("Certificado combinado PEM cargado desde %s", pem_path)

    # ------------------------------------------------------------------
    # Firma
    # ------------------------------------------------------------------

    def sign(self, xml_unsigned: str | bytes) -> bytes:
        """
        Firma el XML unsigned y retorna el XML signed como bytes UTF-8.

        Equivalente a XmlSigned::signXml() en PHP:
          1. Parsea el XML con lxml preservando namespaces.
          2. Busca el nodo ext:ExtensionContent vacío para insertar la firma.
          3. Configura la firma enveloped con C14N y RSA-SHA1.
          4. Firma e incluye el bloque X509Certificate.
        """
        if not _XMLSEC_AVAILABLE:
            raise RuntimeError("python-xmlsec no está instalado.")
        if self._private_key is None:
            raise RuntimeError("Certificado no cargado. Llama load_p12() o load_pem() primero.")

        if isinstance(xml_unsigned, str):
            xml_unsigned = xml_unsigned.encode("utf-8")

        # Parsear preservando namespaces y bom
        parser = etree.XMLParser(remove_blank_text=False, recover=False)
        root = etree.fromstring(xml_unsigned, parser)

        # Leer el ID de firma desde la plantilla UBL (ej: "IDFirma")
        # Equivalente a leer <cac:Signature><cbc:ID> en XmlSigned::signXml()
        signature_id = self._extract_signature_id(root)

        # Encontrar nodo de inserción de firma: ext:ExtensionContent vacío
        sign_node = self._find_signature_node(root)

        # Crear template de firma — TransformInclC14N = C14N regular (no exclusivo)
        # equivalente a XMLSecurityDSig::C14N en PHP
        signature = xmlsec.template.create(
            root,
            c14n_method=xmlsec.constants.TransformInclC14N,
            sign_method=xmlsec.constants.TransformRsaSha1,
            ns="ds",
        )
        # Establecer el atributo Id en <ds:Signature> para que coincida con
        # la referencia en <cac:Signature><cbc:ID> del documento UBL
        if signature_id:
            signature.set("Id", signature_id)
        sign_node.append(signature)

        # Referencia URI="" apunta al documento completo — PHP solo agrega ENVELOPED,
        # sin transformación C14N adicional en la referencia
        ref = xmlsec.template.add_reference(
            signature,
            digest_method=xmlsec.constants.TransformSha1,
            uri="",
        )
        xmlsec.template.add_transform(ref, xmlsec.constants.TransformEnveloped)

        # KeyInfo con X509Data
        key_info = xmlsec.template.ensure_key_info(signature)
        xmlsec.template.add_x509_data(key_info)

        # Ejecutar firma
        ctx = xmlsec.SignatureContext()
        ctx.key = self._private_key
        ctx.sign(signature)

        signed_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", pretty_print=False)
        logger.debug("XML firmado correctamente (%d bytes)", len(signed_xml))
        return signed_xml

    # ------------------------------------------------------------------
    # Verificación
    # ------------------------------------------------------------------

    def verify(self, xml_signed: str | bytes) -> bool:
        """
        Verifica la firma del XML.

        Retorna True si la firma es válida, False en caso contrario.
        Útil en tests unitarios e integrales.
        """
        if not _XMLSEC_AVAILABLE:
            raise RuntimeError("python-xmlsec no está instalado.")

        if isinstance(xml_signed, str):
            xml_signed = xml_signed.encode("utf-8")

        parser = etree.XMLParser(remove_blank_text=False)
        root = etree.fromstring(xml_signed, parser)

        # Buscar nodo Signature
        signature_node = xmlsec.tree.find_node(root, xmlsec.constants.NodeSignature)
        if signature_node is None:
            return False

        ctx = xmlsec.SignatureContext()
        if self._private_key:
            ctx.key = self._private_key
        try:
            ctx.verify(signature_node)
            return True
        except xmlsec.Error:
            return False

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _extract_signature_id(root: "etree._Element") -> str:
        """
        Lee el texto de <cac:Signature><cbc:ID> del XML UBL.

        Se usa para establecer el atributo Id en <ds:Signature> y que coincida
        con la referencia de firma declarada en el documento.
        Equivalente a la lectura de $this->signatureId en XmlSigned::signXml().
        """
        NS_CAC = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
        NS_CBC = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
        for sig_el in root.findall(f".//{{{NS_CAC}}}Signature"):
            id_el = sig_el.find(f"{{{NS_CBC}}}ID")
            if id_el is not None and id_el.text:
                return id_el.text.strip()
        return ""

    @staticmethod
    def _find_signature_node(root: "etree._Element") -> "etree._Element":
        """
        Localiza el nodo ext:ExtensionContent vacío donde se inserta la firma.

        Equivalente a XmlSigned::getNodeSign() en PHP.
        Si no existe, retorna el elemento raíz.
        """
        namespaces = {"ext": _NS_EXT}
        # Buscar el segundo ExtensionContent vacío (patrón SUNAT)
        nodes = root.findall(".//ext:ExtensionContent", namespaces)
        for node in nodes:
            if len(node) == 0 and (node.text is None or not node.text.strip()):
                return node
        # Fallback: raíz del documento
        return root


def compute_digest(xml_bytes: bytes, algorithm: str = "sha256") -> str:
    """
    Calcula el digest del XML (equivalente a XmlHash::getHash en PHP).

    Args:
        xml_bytes: XML signed como bytes.
        algorithm: 'sha1' | 'sha256' (por defecto sha256).

    Returns:
        Digest en base64.
    """
    h = hashlib.new(algorithm, xml_bytes)
    return base64.b64encode(h.digest()).decode("ascii")
