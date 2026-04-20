# =============================================================================
# Dockerfile multi-stage para sunat-fastapi
#
# Stages:
#   base        → imagen base con dependencias de sistema (libxmlsec1)
#   builder     → instala paquetes Python en un prefijo aislado
#   development → hot-reload, código montado como volumen
#   production  → imagen mínima con usuario no-root, sin código fuente extra
# =============================================================================

# ---- Stage: base ----
FROM python:3.11-slim AS base

# Variables de entorno comunes a todos los stages
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONFAULTHANDLER=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

# Dependencias de sistema necesarias para xmlsec y cryptography
RUN apt-get update && apt-get install -y --no-install-recommends \
        libxmlsec1-dev \
        libxmlsec1-openssl \
        libxml2-dev \
        libxslt1-dev \
        zlib1g-dev \
        pkg-config \
        build-essential \
        gcc \
        libssl-dev \
        curl \
        netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# ---- Stage: builder ----
FROM base AS builder

COPY requirements.txt .

# Instalar dependencias en un prefijo aislado para copiar luego
RUN pip install --upgrade pip && \
    # Force building lxml and xmlsec from source so they link against the system libxml2/xmlsec
    pip install --no-cache-dir --prefix=/install --no-binary=lxml,xmlsec -r requirements.txt

# ---- Stage: development ----
FROM base AS development

LABEL maintainer="sunat-fastapi-dev"
LABEL description="Entorno de desarrollo con hot-reload"

# Copiar paquetes instalados desde builder
COPY --from=builder /install /usr/local

# Copiar entrypoint (corre migraciones + inicia servidor)
COPY docker/api/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# El código fuente se monta como volumen en docker-compose (hot-reload)
EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]

# ---- Stage: production ----
FROM base AS production

LABEL maintainer="sunat-fastapi"
LABEL description="Imagen de producción — usuario no-root, sin herramientas de dev"

# Copiar paquetes instalados desde builder
COPY --from=builder /install /usr/local

# Crear usuario no-root
RUN addgroup --gid 1001 appgroup && \
    adduser --uid 1001 --gid 1001 --disabled-password --gecos "" appuser

# Copiar código fuente con permisos del usuario app
COPY --chown=appuser:appgroup . .

# Copiar y configurar entrypoint
COPY --chown=appuser:appgroup docker/api/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER appuser

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
# 4 workers; ajustar con WEB_CONCURRENCY en .env según núcleos disponibles
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "4"]
