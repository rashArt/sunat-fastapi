"""
Proveedor de mensajes de error SUNAT.

Carga los códigos de error desde CodeErrors.xml (portado de
XmlErrorCodeProvider.php en CoreFacturalo/WS/Validator/).
"""
from __future__ import annotations

from functools import lru_cache
from pathlib import Path

_XML_PATH = Path(__file__).parent / "CodeErrors.xml"
_HTTP_ERROR_MESSAGES: dict[int, str] = {
    200: "Operación exitosa",
    400: "Errores de cliente (Bad Request)",
    401: "Errores de cliente (Unauthorized)",
    403: "Errores de servidor (Forbidden)",
    404: "Errores de cliente (Not Found)",
    407: "Errores de cliente (Proxy Authentication Required)",
    409: "Errores de cliente (Conflict)",
    500: "Errores de servidor (Internal Server Error)",
    502: "Errores de servidor (Bad Gateway)",
}


@lru_cache(maxsize=1)
def _load_all() -> dict[str, str]:
    """Carga todos los códigos de error desde el XML. Se cachea en la primera llamada."""
    if not _XML_PATH.exists():
        return {}
    try:
        from lxml import etree
        root = etree.parse(str(_XML_PATH)).getroot()
        return {
            node.get("code"): node.text or ""
            for node in root.findall("error")
        }
    except Exception:
        return {}


def get_error_message(code: str) -> str:
    """
    Retorna el mensaje de error SUNAT para un código dado.

    Retorna '' si el código no existe en el catálogo.
    """
    return _load_all().get(str(code), "")


def get_http_error_message(status_code: int) -> str:
    """Retorna el mensaje para un código HTTP de respuesta PSE/OSE."""
    return _HTTP_ERROR_MESSAGES.get(status_code, f"Error HTTP {status_code}")


def get_all_errors() -> dict[str, str]:
    """Retorna el diccionario completo de códigos de error SUNAT."""
    return dict(_load_all())
