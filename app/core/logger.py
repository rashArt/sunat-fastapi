"""
Configuración de logging estructurado en formato JSON.
"""
import logging
import sys
from pythonjsonlogger.json import JsonFormatter

from app.core.config import settings


def configure_logging() -> None:
    """Inicializa el handler JSON para todos los logs de la aplicación."""
    log_level = getattr(logging, settings.LOG_LEVEL.upper(), logging.INFO)

    handler = logging.StreamHandler(sys.stdout)
    formatter = JsonFormatter(
        fmt="%(asctime)s %(levelname)s %(name)s %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%S",
    )
    handler.setFormatter(formatter)

    root_logger = logging.getLogger()
    root_logger.handlers.clear()
    root_logger.addHandler(handler)
    root_logger.setLevel(log_level)
