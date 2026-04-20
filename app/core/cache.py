"""
Capa de caché Redis para configuraciones de compañías y datos de sesión.

Expone operaciones get/set/delete con degradación elegante:
si Redis no está disponible, las operaciones son no-op y retornan None.

Implementa un circuit-breaker simple: tras un fallo, no reintenta
la conexión hasta pasado RETRY_AFTER_SECONDS (evita el error 'Broken pipe'
por reconexiones inmediatas con socket en estado inconsistente).
"""
import json
import logging
import time
from typing import Optional

import redis

from app.core.config import settings

logger = logging.getLogger(__name__)

# TTL por defecto para los datos de compañía (5 minutos)
COMPANY_CACHE_TTL = 300

# Segundos de espera antes de reintentar la conexión tras un fallo (circuit-breaker)
_RETRY_AFTER_SECONDS = 30

# Estado del circuit-breaker
_redis_client: Optional[redis.Redis] = None
_redis_disabled_until: float = 0.0  # monotonic timestamp; 0 = disponible inmediatamente


def get_redis_client() -> Optional[redis.Redis]:
    """
    Retorna el cliente Redis singleton con conexión lazy y circuit-breaker.

    Si Redis no está disponible, retorna None (modo degradado).
    Tras un fallo no reintenta la conexión hasta pasado _RETRY_AFTER_SECONDS
    para evitar errores de tipo 'Broken pipe' por reconexiones inmediatas.
    """
    global _redis_client, _redis_disabled_until

    # Circuit-breaker cerrado: esperamos antes de reintentar
    if _redis_client is None and time.monotonic() < _redis_disabled_until:
        return None

    if _redis_client is None:
        try:
            client = redis.from_url(
                settings.REDIS_URL,
                decode_responses=True,
                socket_connect_timeout=2,
                socket_timeout=2,
            )
            client.ping()
            _redis_client = client
            _redis_disabled_until = 0.0
            logger.info("Conexión Redis establecida: %s", settings.REDIS_URL)
        except Exception as exc:
            logger.warning(
                "Redis no disponible, reintentando en %ds: %s",
                _RETRY_AFTER_SECONDS,
                exc,
            )
            _redis_client = None
            _redis_disabled_until = time.monotonic() + _RETRY_AFTER_SECONDS

    return _redis_client


def _handle_redis_error(exc: Exception) -> None:
    """Registra el error y abre el circuit-breaker para evitar reconexiones inmediatas."""
    global _redis_client, _redis_disabled_until
    logger.warning("Error Redis, desconectando por %ds: %s", _RETRY_AFTER_SECONDS, exc)
    _redis_client = None
    _redis_disabled_until = time.monotonic() + _RETRY_AFTER_SECONDS


def cache_get(key: str) -> Optional[dict]:
    """
    Recupera un valor del caché Redis.
    Retorna None si la clave no existe, expiró, o hay un error de conexión.
    """
    client = get_redis_client()
    if client is None:
        return None
    try:
        raw = client.get(key)
        if raw is not None:
            return json.loads(raw)
    except Exception as exc:
        _handle_redis_error(exc)
    return None


def cache_set(key: str, data: dict, ttl: int = COMPANY_CACHE_TTL) -> None:
    """
    Guarda un diccionario en Redis con TTL en segundos.
    Usa json.dumps con default=str para serializar fechas/Decimals.
    """
    client = get_redis_client()
    if client is None:
        return
    try:
        client.setex(key, ttl, json.dumps(data, default=str))
    except Exception as exc:
        _handle_redis_error(exc)


def cache_delete(key: str) -> None:
    """Elimina una clave del caché Redis (invalidación tras escritura)."""
    client = get_redis_client()
    if client is None:
        return
    try:
        client.delete(key)
    except Exception as exc:
        _handle_redis_error(exc)


def reset_client() -> None:
    """
    Reinicia el cliente singleton y el circuit-breaker.
    Útil en tests o para forzar reconexión manual.
    """
    global _redis_client, _redis_disabled_until
    _redis_client = None
    _redis_disabled_until = 0.0
