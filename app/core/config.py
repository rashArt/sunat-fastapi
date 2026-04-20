"""
Configuración central de la aplicación.

Carga variables de entorno desde .env usando pydantic-settings.
"""
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    APP_NAME: str = "sunat-fastapi"
    APP_VERSION: str = "0.1.0"
    SECRET_KEY: str = "cambia-esto-en-produccion"

    # Entorno SUNAT: "demo" | "prod"
    SUNAT_MODE: str = "demo"

    # Storage
    STORAGE_DRIVER: str = "local"  # "local" | "s3"
    STORAGE_LOCAL_PATH: str = "./storage"

    # Base de datos — PostgreSQL en Docker; SQLite como fallback local
    DATABASE_URL: str = "postgresql://sunat:sunat@postgres:5432/sunat"

    # Redis — disponible para fase 2 (colas de tickets, caché de sesiones)
    REDIS_URL: str = "redis://redis:6379/0"

    # Servidor
    WEB_CONCURRENCY: int = 4       # workers uvicorn en producción

    # Logging
    LOG_LEVEL: str = "INFO"


settings = Settings()
