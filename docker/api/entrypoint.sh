#!/bin/sh
# Entrypoint del contenedor API.
# 1. Asegura el directorio de trabajo /app (montado en dev como volumen)
# 2. Exporta PYTHONPATH para que alembic y los imports encuentren el paquete `app`
# 3. Espera activamente a que PostgreSQL acepte conexiones (evita race condition en restart)
# 4. Ejecuta migraciones Alembic (idempotente — seguro en multi-replica)
# 5. Delega al CMD del Dockerfile

set -e

echo "==> Preparando entorno de ejecución..."
cd /app || true
export PYTHONPATH="/app:${PYTHONPATH:-}"

# ---------------------------------------------------------------------------
# Esperar a que PostgreSQL esté listo para aceptar conexiones.
# Extrae host y puerto de DATABASE_URL para no depender de variables extras.
# Formato esperado: postgresql://user:pass@host:port/db
# ---------------------------------------------------------------------------
DB_HOST=$(echo "${DATABASE_URL}" | sed 's|.*@\([^:/]*\).*|\1|')
DB_PORT=$(echo "${DATABASE_URL}" | sed 's|.*:\([0-9]*\)/.*|\1|')
DB_PORT=${DB_PORT:-5432}

echo "==> Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}..."
MAX_RETRIES=30
RETRY=0
until nc -z "${DB_HOST}" "${DB_PORT}" 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ "${RETRY}" -ge "${MAX_RETRIES}" ]; then
        echo "ERROR: PostgreSQL no respondió después de ${MAX_RETRIES} intentos. Abortando."
        exit 1
    fi
    echo "   intento ${RETRY}/${MAX_RETRIES} — reintentando en 2s..."
    sleep 2
done
echo "==> PostgreSQL disponible."

echo "==> Ejecutando migraciones Alembic..."
alembic upgrade head

echo "==> Iniciando servidor..."
exec "$@"
