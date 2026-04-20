"""
Constructor de XML para comprobantes SUNAT.

Renderiza templates Jinja2 ubicados en app/templates/xml/ y devuelve
el XML unsigned como string UTF-8, listo para ser firmado.

Equivalente a Template::xml() + XmlFormat::format() en PHP.
"""
import logging
import re
from decimal import Decimal
from pathlib import Path
from typing import Any, Dict

from jinja2 import Environment, FileSystemLoader, Undefined

logger = logging.getLogger(__name__)

# Directorio de plantillas XML
_TEMPLATES_DIR = Path(__file__).resolve().parent.parent / "templates" / "xml"

# Entorno Jinja2 con modo estricto para detectar variables no definidas
_jinja_env = Environment(
    loader=FileSystemLoader(str(_TEMPLATES_DIR)),
    undefined=Undefined,
    autoescape=False,  # XML lo gestiona el template; autoescape HTML no aplica
    trim_blocks=True,
    lstrip_blocks=True,
)

# Mapeo tipo de documento → nombre de plantilla .xml.j2
_TEMPLATE_MAP: Dict[str, str] = {
    "invoice": "invoice.xml.j2",
    "credit": "credit.xml.j2",
    "debit": "debit.xml.j2",
    "summary": "summary.xml.j2",
    "voided": "voided.xml.j2",
    "retention": "retention.xml.j2",
    "perception": "perception.xml.j2",
    "dispatch": "dispatch.xml.j2",
    "dispatch2": "dispatch2.xml.j2",
    "purchase_settlement": "purchase_settlement.xml.j2",
}


def _format_decimal(value: Any, places: int = 2) -> str:
    """
    Formatea un Decimal con el número de decimales requerido por SUNAT.

    SUNAT exige punto decimal y sin separadores de miles.
    """
    if value is None:
        return "0." + "0" * places
    d = Decimal(str(value))
    fmt = f".{places}f"
    return format(d, fmt)


def _truncate(text: str, max_length: int) -> str:
    """Trunca texto al límite máximo permitido por SUNAT."""
    if text and len(text) > max_length:
        return text[:max_length]
    return text or ""


def _register_filters(env: Environment) -> None:
    """Registra filtros personalizados en el entorno Jinja2."""
    env.filters["decimal2"] = lambda v: _format_decimal(v, 2)
    env.filters["decimal6"] = lambda v: _format_decimal(v, 6)
    env.filters["decimal10"] = lambda v: _format_decimal(v, 10)
    env.filters["trunc"] = _truncate
    env.filters["xml_escape"] = lambda s: (
        str(s)
        .replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
        .replace("'", "&apos;")
    )


_register_filters(_jinja_env)
# Registrar global enumerate para iterar con índice en templates
_jinja_env.globals["enumerate"] = enumerate


def get_affectation_tribute(affectation_id: Any) -> dict:
    """
    Devuelve el tributo (id, code, name) según el tipo de afectación IGV.

    Equivalente Python de FunctionTribute::getByAffectation() en PHP.
    Usado en los templates Jinja2 para determinar el TaxScheme de cada línea.

    Tabla de afectación → código de tributo SUNAT:
      10 → 1000 (IGV/VAT)
      17 → 1016 (IVAP/VAT)
      20 → 9997 (EXO/VAT)
      30 → 9998 (INA/FRE)
      40 → 9995 (EXP/FRE)
      otro → 9996 (GRA/FRE)
    """
    _code_taxes = {
        "1000": ("VAT", "IGV"),
        "1016": ("VAT", "IVAP"),
        "2000": ("EXC", "ISC"),
        "9995": ("FRE", "EXP"),
        "9996": ("FRE", "GRA"),
        "9997": ("VAT", "EXO"),
        "9998": ("FRE", "INA"),
        "9999": ("OTH", "OTROS"),
    }
    try:
        code = int(str(affectation_id))
    except (ValueError, TypeError):
        code = 0

    if code == 10:
        tribute_code = "1000"
    elif code == 17:
        tribute_code = "1016"
    elif code == 20:
        tribute_code = "9997"
    elif code == 30:
        tribute_code = "9998"
    elif code == 40:
        tribute_code = "9995"
    else:
        tribute_code = "9996"

    tax_code, tax_name = _code_taxes.get(tribute_code, ("OTH", "OTROS"))
    return {"id": tribute_code, "code": tax_code, "name": tax_name}


_jinja_env.globals["get_affectation_tribute"] = get_affectation_tribute


def _clean_xml(xml_str: str) -> str:
    """
    Limpia el XML generado por Jinja2:
    - Elimina líneas en blanco extra que Jinja2 puede introducir.
    - Asegura declaración UTF-8 al inicio.

    Equivalente parcial de XmlFormat::format() en PHP.
    """
    # Reemplazar múltiples líneas vacías consecutivas
    xml_str = re.sub(r"\n{3,}", "\n\n", xml_str)
    # Eliminar espacios al final de cada línea
    xml_str = "\n".join(line.rstrip() for line in xml_str.splitlines())
    # Asegurar que inicia con la declaración XML
    if not xml_str.strip().startswith("<?xml"):
        xml_str = '<?xml version="1.0" encoding="UTF-8"?>\n' + xml_str
    return xml_str.strip() + "\n"


def build_xml(doc_type: str, context: Dict[str, Any]) -> str:
    """
    Genera el XML unsigned del comprobante.

    Args:
        doc_type: Tipo de documento ("invoice", "credit", etc.)
        context:  Diccionario con todos los datos necesarios para el template.

    Returns:
        String XML unsigned (UTF-8).

    Raises:
        ValueError: Si el tipo de documento no tiene plantilla asignada.
        jinja2.TemplateNotFound: Si el archivo .j2 no existe aún.
    """
    template_name = _TEMPLATE_MAP.get(doc_type)
    if not template_name:
        raise ValueError(
            f"No existe plantilla para el tipo de documento '{doc_type}'. "
            f"Tipos disponibles: {list(_TEMPLATE_MAP.keys())}"
        )

    logger.debug("Generando XML", extra={"doc_type": doc_type, "template": template_name})

    template = _jinja_env.get_template(template_name)
    raw_xml = template.render(**context)
    return _clean_xml(raw_xml)


def get_template_path(doc_type: str) -> Path:
    """Retorna la ruta del archivo de plantilla para el tipo dado."""
    template_name = _TEMPLATE_MAP.get(doc_type, f"{doc_type}.xml.j2")
    return _TEMPLATES_DIR / template_name
