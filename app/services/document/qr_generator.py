"""
Generador de códigos QR para comprobantes electrónicos SUNAT.

Equivalente a QrCodeGenerate.php en CoreFacturalo.
El QR se retorna como imagen PNG en base64.
"""
from __future__ import annotations

import base64
import io
import logging

logger = logging.getLogger(__name__)

try:
    import qrcode
    from qrcode.image.pure import PyPNGImage
    _QR_AVAILABLE = True
except ImportError:
    _QR_AVAILABLE = False
    logger.warning("qrcode no disponible. La generación de QR no funcionará.")


def generate_qr(text: str, box_size: int = 4, border: int = 4) -> str:
    """
    Genera un código QR a partir del texto dado y retorna la imagen en base64.

    Args:
        text:     Texto del QR (formato SUNAT: RUC|tipo|serie|...|hash).
        box_size: Tamaño de cada cuadro en píxeles.
        border:   Borde en cuadros alrededor del QR.

    Returns:
        String base64 de la imagen PNG.
    """
    if not _QR_AVAILABLE:
        raise RuntimeError("Instala el paquete 'qrcode[pil]' para generar QR.")

    qr = qrcode.QRCode(
        version=None,  # auto-detectar tamaño
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=box_size,
        border=border,
    )
    qr.add_data(text)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white")

    buffer = io.BytesIO()
    img.save(buffer, format="PNG")
    buffer.seek(0)

    return base64.b64encode(buffer.read()).decode("ascii")
