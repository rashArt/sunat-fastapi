# sunat-fastapi

Servicio FastAPI como puente para el envío de comprobantes electrónicos a **SUNAT (Perú)**.

## Pipeline

```
POST /api/v1/documents
        │
        ▼
  Validar JSON (Pydantic)
        │
        ▼
  Generar XML unsigned (Jinja2 UBL 2.1)
        │
        ▼
  Firmar XML (python-xmlsec / PSE externo)
        │
        ▼
  Enviar a SUNAT / OSE / PSE (zeep SOAP)
        │
        ▼
  Parsear CDR → estado
        │
        ▼
  Retornar artefactos (xml_unsigned, xml_signed, cdr, hash, qr)
```

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET`  | `/health` | Estado del servicio |
| `POST` | `/api/v1/companies` | Registrar/actualizar compañía |
| `POST` | `/api/v1/documents` | Procesar comprobante (pipeline completo) |
| `POST` | `/api/v1/tickets` | Procesar comprobante asíncrono |

Documentación interactiva: `http://localhost:8000/docs`

---

## Requisitos

- **Docker** ≥ 24
- **Docker Compose** ≥ 2.20 (incluido en Docker Desktop)

> No se requiere Python ni ninguna dependencia en el host. Todo corre dentro de contenedores.

---

## Instalación y arranque rápido

### 1. Clonar el repositorio

```bash
git clone <repo-url>
cd sunat-fastapi
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
# Editar .env — como mínimo cambiar POSTGRES_PASSWORD y DATABASE_URL
```

Variables clave en `.env`:

| Variable | Descripción | Default |
|----------|-------------|---------|
| `SUNAT_MODE` | `demo` o `prod` | `demo` |
| `POSTGRES_PASSWORD` | Contraseña base de datos | *(requerido)* |
| `DATABASE_URL` | Cadena de conexión PostgreSQL | `postgresql://sunat:...@postgres:5432/sunat` |
| `REDIS_URL` | Cadena de conexión Redis | `redis://redis:6379/0` |
| `STORAGE_LOCAL_PATH` | Directorio certificados y XML | `/app/storage` |
| `LOG_LEVEL` | Nivel de logging | `INFO` |

### 3. Levantar los contenedores

```bash
docker compose up -d
```

Al arrancar, el contenedor `api` ejecuta automáticamente `alembic upgrade head` antes de iniciar Uvicorn.

Servicios disponibles:

| Servicio | URL / Puerto |
|----------|-------------|
| API FastAPI | `http://localhost:8000` |
| Swagger UI | `http://localhost:8000/docs` |
| PostgreSQL | `localhost:5432` |
| Redis | `localhost:6379` |

### 4. Ver logs

```bash
docker compose logs -f api
docker compose logs -f postgres
```

### 5. Ejecutar tests

```bash
docker compose exec api pytest -v
```

### 6. Detener

```bash
docker compose down          # mantiene volúmenes (datos persistidos)
docker compose down -v       # destruye también los volúmenes
```

---

## Estructura Docker

```
docker/
├── api/
│   ├── Dockerfile           # Multi-stage: base → builder → development → production
│   ├── entrypoint.sh        # Corre migraciones Alembic + inicia Uvicorn
│   └── .dockerignore
├── postgres/
│   └── postgresql.conf      # Configuración optimizada (memoria, WAL, logging)
└── redis/
    └── redis.conf           # AOF, maxmemory 256 MB, evicción allkeys-lru

docker-compose.yml           # Entorno de desarrollo (hot-reload, puertos expuestos)
docker-compose.prod.yml      # Overrides de producción (imagen prod, límites de recursos)
```

### Stages del Dockerfile

| Stage | Uso | Características |
|-------|-----|-----------------|
| `base` | Interno | Python 3.11-slim + libxmlsec1 + gcc |
| `builder` | Interno | Instala dependencias pip en `/install` |
| `development` | Dev | Hot-reload, código montado como volumen |
| `production` | Prod | Usuario no-root, 4 workers Uvicorn, sin devtools |

---

## Producción con Docker Compose

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Diferencias del override de producción:
- Stage `production` del Dockerfile (usuario no-root, sin montaje de código)
- Puertos de PostgreSQL y Redis **no expuestos** al host
- Límites de CPU y memoria por contenedor


---

## Flujo de uso (ejemplo rápido)

### 1. Registrar compañía

```bash
curl -X POST http://localhost:8000/api/v1/companies \
  -H "Content-Type: application/json" \
  -d '{
    "legal_number": "20600000001",
    "legal_name": "Mi Empresa S.A.C.",
    "sunat_mode": "demo",
    "soap_send_type": "sunat",
    "soap_username": "20600000001MODDATOS",
    "soap_password": "mi_clave_sol",
    "certificate_b64": "<BASE64_DEL_CERTIFICADO_P12>"
  }'
```

### 2. Enviar factura

```bash
curl -X POST http://localhost:8000/api/v1/documents \
  -H "Content-Type: application/json" \
  -d '{
    "company_ruc": "20600000001",
    "type": "invoice",
    "document_type_id": "01",
    "series": "F001",
    "number": "1",
    "date_of_issue": "2026-04-01",
    "establishment": {
      "address": "Av. Principal 123",
      "district": "Lima",
      "province": "Lima",
      "department": "Lima",
      "ubigeo": "150101",
      "country_id": "PE"
    },
    "customer": {
      "identity_document_type_id": "6",
      "number": "20100000001",
      "name": "Cliente S.A.C.",
      "address": "Av. Cliente 456"
    },
    "currency_type_id": "PEN",
    "items": [{
      "description": "Producto A",
      "unit_type_id": "NIU",
      "quantity": "1",
      "unit_value": "100.00",
      "unit_price": "118.00",
      "price_type_id": "01",
      "affectation_igv_type_id": "10",
      "total_base_igv": "100.00",
      "percentage_igv": "18.00",
      "total_igv": "18.00",
      "total_taxes": "18.00",
      "total_value": "100.00",
      "total": "118.00"
    }],
    "totals": {
      "total_taxed": "100.00",
      "total_igv": "18.00",
      "total_taxes": "18.00",
      "total_value": "100.00",
      "total": "118.00"
    },
    "actions": {
      "send_xml_signed": true
    }
  }'
```

### Respuesta esperada

```json
{
  "success": true,
  "filename": "20600000001-01-F001-1",
  "state_type_id": "05",
  "state_description": "Aceptado",
  "sunat_code": "0",
  "sunat_description": "La Factura numero F001-1, ha sido aceptada",
  "xml_unsigned_b64": "PD94bWwgdmV...",
  "xml_signed_b64": "PD94bWwgdmV...",
  "cdr_b64": "UEsDBBQA...",
  "hash": "abc123...",
  "qr_b64": "iVBORw0KGgo..."
}
```

> Para pruebas de integración usa el archivo [tests/payloads/test.http](tests/payloads/test.http) con la extensión REST Client de VS Code.

---

## Estructura del proyecto

```
app/
├── main.py                    # FastAPI app + middleware
├── api/v1/
│   ├── companies.py           # POST /companies
│   ├── documents.py           # POST /documents
│   └── tickets.py             # POST /tickets
├── core/
│   ├── config.py              # Configuración (Pydantic Settings)
│   ├── db.py                  # SQLAlchemy engine + session
│   └── logger.py              # Logging JSON estructurado
├── models/
│   └── company_model.py       # ORM Company
├── schemas/
│   ├── company.py             # Pydantic request/response
│   └── document.py
└── services/
    ├── pipeline.py            # Orquestador del pipeline
    ├── xml_builder.py         # Generador XML (Jinja2 UBL 2.1)
    ├── signer.py              # Firma XML (xmlsec)
    ├── sender.py              # Cliente SOAP (zeep)
    ├── company_store.py       # Registro de compañías (mem + disco)
    └── storage.py             # Persistencia de artefactos

docker/
├── api/Dockerfile             # Multi-stage (dev + prod)
├── postgres/postgresql.conf   # PostgreSQL tuneado
└── redis/redis.conf           # Redis con AOF

alembic/
└── versions/                  # Migraciones de base de datos

tests/                         # Tests unitarios e integración
storage/                       # Certificados y XMLs (volumen Docker)
```
