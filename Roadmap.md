## Fase 1 — Configuración por empresa + respuesta híbrida (una sola fase)
### Objetivo:

Mantener la experiencia actual “rápida”, pero hacerla configurable y robusta.

### Hacer:
Extender company con configuración operativa:
* response_mode (hybrid por defecto)
* wait_seconds
* auto_poll_ticket
* ticket_wait_seconds
* ticket_poll_interval_ms
* api_token
* status

Reglas:
* Si no se envían en JSON al crear/actualizar empresa → usar defaults internos
* Si no se envían en POST /documents → usar lo configurado en company

Aplicación en pipeline:
* intentar respuesta completa en línea
* si documento async y auto_poll_ticket=true:
* consultar ticket automáticamente dentro de la ventana
* si no se resuelve en tiempo:
* retornar processing / ticket_pending

> 👉 Todo lo relacionado con tiempos, espera y comportamiento de respuesta queda centralizado aquí.

## Fase 2 — Persistencia real (PostgreSQL)
### Objetivo:

Dejar de depender solo del pipeline en memoria y del filesystem.

### Hacer:
* Crear tablas mínimas
* En documents guardar:
    * document_id
    * company_id
    * external_id
    * filename
    * processed_at
    * attempts_count
    * entre otros

> 👉 Esto te da trazabilidad, soporte y base para crecimiento.

## Fase 3 — Idempotencia y control de duplicados
### Objetivo:

Evitar doble emisión por retries, timeouts o clientes impacientes.

### Hacer:
* external_id obligatorio por documento
* índice único por (company_id, external_id)
* si el documento ya existe:
    * si sigue en proceso → devolver estado actual
    * si ya terminó → devolver resultado actual
* opcional después:
    * soporte Idempotency-Key

> 👉 Esto es crítico en facturación electrónica.

## Fase 4 — Refactor del pipeline por etapas
### Objetivo:

Convertir el pipeline actual en un flujo controlable, medible y reutilizable.

Separar en pasos explícitos:
1. validar entrada
2. resolver configuración efectiva (company + request)
3. generar filename
4. generar XML unsigned
5. persistir unsigned
6. firmar (local/PSE)
7. persistir signed
8. calcular hash
9. generar QR
10. enviar SUNAT/OSE
11. si aplica, auto-consulta ticket
12. parsear CDR
13. mapear estado
14. persistir resultado final
15. opcional PDF
16. construir response según modo

> 👉 Así podrás cortar por tiempo, reintentar y mover luego a worker sin rehacer todo.

## Fase 5 — Separar artefactos del response principal
### Objetivo:

Reducir peso de respuesta y preparar escalabilidad.

### Hacer:
* Mantener compatibilidad, pero evolucionar:
* permitir seguir retornando artefactos si el modo lo requiere
* agregar opción de respuesta ligera
* crear endpoints de artefactos:
    * XML unsigned
    * XML signed
    * CDR
    * QR
    * PDF

Recomendación:
* modo legacy: retorna base64 (compatibilidad)
* modo moderno: retorna metadata + consulta por endpoint

> 👉 No rompes clientes actuales, pero mejoras el diseño.

## Fase 6 — Storage desacoplado
### Objetivo:

Preparar despliegue multi-instancia y evitar dependencia del disco local.

#### Evolución:
Corto plazo:
* mantener filesystem si necesitas velocidad de transición

Siguiente paso:
* abstraer storage.py para soportar:
    * local filesystem
    * MinIO / S3 compatible

Guardar fuera de DB:
* XML unsigned
* XML signed
* CDR
* QR
* PDF

> 👉 El código debe dejar de asumir “disco local siempre”.

## Fase 7 — Seguridad pragmática
### Objetivo:

Subir el nivel sin complicar el consumo.

Hacer primero:
* X-API-Key
* credencial por empresa o integrador
* estado activo/inactivo
* rotación manual
* rate limit básico

Dejar después:
* JWT
* scopes avanzados
* tokens temporales

> 👉 Primero simple y estable, luego enterprise.

## Fase 8 — Cola interna y fallback asíncrono
### Objetivo:

Que la API no dependa 100% del tiempo de SUNAT, sin cambiar la UX del cliente.

### Hacer:
* Redis
* worker (ARQ o Celery)
* cuando POST /documents no completa:
    * persistir estado
    * dejar trabajo pendiente
    * continuar en background

Casos ideales para worker:
* ticket que no resolvió en ventana
* reintentos por timeout
* recuperación de CDR
* generación de PDF tardía

> 👉 Internamente moderno, externamente familiar.

## Fase 9 — Endpoints de consulta y soporte técnico
### Objetivo:

Dar fallback limpio y soporte operativo.

Mantener / agregar:
* GET /documents/{document_id}
* GET /documents/by-external-id/{external_id}
* GET /tickets/{ticket}
* GET /documents/{id}/artifacts

> 👉 Esto reduce soporte manual y hace el sistema más confiable.

## Fase 10 — Observabilidad y mantenimiento
### Objetivo:

Poder operar el sistema sin adivinar.

### Hacer:
* request_id / correlation_id
* logs estructurados por documento
* tiempos por etapa del pipeline
* métricas:
    * tiempo total
    * tiempo firma
    * tiempo SUNAT
    * tiempo ticket polling
    * tasa de aceptación/rechazo
* alerta de certificado próximo a vencer

> 👉 Sin esto, escalar duele.

## Resumen corto

Tu v2 técnica debería quedar así:

* misma experiencia rápida para el cliente
* configurable por empresa
* pipeline controlado por etapas
* persistencia real
* idempotencia obligatoria
* artefactos desacoplados
* fallback async interno
* consultas simples
* seguridad pragmática
* observabilidad desde temprano


# Fases generadas/encontradas durante el desarrollo

## Fase A — Camino a producción: Kubernetes + Traefik

### Visión general

```
Internet
   │
   ▼
Traefik IngressController  (TLS termination, routing, rate-limit)
   │
   ├─▶ sunat-api  Deployment  (N réplicas, HPA)
   │        │
   │        ├─▶ postgres  StatefulSet  (PVC para datos)
   │        └─▶ redis     Deployment   (PVC para AOF)
   └─▶ /metrics  → Prometheus + Grafana
```

### Pasos para migrar a Kubernetes

#### 1. Construir y publicar la imagen

```bash
# Construir imagen de producción
docker build \
  --file docker/api/Dockerfile \
  --target production \
  --tag ghcr.io/<org>/sunat-fastapi:1.0.0 \
  .

# Publicar al registry
docker push ghcr.io/<org>/sunat-fastapi:1.0.0
```

#### 2. Crear Secrets en Kubernetes

```bash
kubectl create namespace sunat

kubectl create secret generic sunat-env \
  --namespace sunat \
  --from-env-file=.env
```

> En producción usar **Sealed Secrets** o **External Secrets Operator** (AWS Parameter Store, HashiCorp Vault) en lugar de secrets planos.

#### 3. Manifiestos mínimos

```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: sunat-api
  namespace: sunat
spec:
  replicas: 2
  selector:
    matchLabels:
      app: sunat-api
  template:
    metadata:
      labels:
        app: sunat-api
    spec:
      containers:
        - name: api
          image: ghcr.io/<org>/sunat-fastapi:1.0.0
          ports:
            - containerPort: 8000
          envFrom:
            - secretRef:
                name: sunat-env
          readinessProbe:
            httpGet:
              path: /health
              port: 8000
            initialDelaySeconds: 10
          resources:
            requests:
              cpu: "250m"
              memory: "128Mi"
            limits:
              cpu: "1000m"
              memory: "512Mi"
```

```yaml
# ingressroute.yaml  (Traefik CRD)
apiVersion: traefik.io/v1alpha1
kind: IngressRoute
metadata:
  name: sunat-api
  namespace: sunat
spec:
  entryPoints:
    - websecure
  routes:
    - match: Host(`api.tudominio.com`)
      kind: Rule
      services:
        - name: sunat-api
          port: 8000
  tls:
    certResolver: letsencrypt
```

#### 4. Base de datos y Redis

Para PostgreSQL y Redis en Kubernetes se recomienda:

| Componente | Opción recomendada |
|---|---|
| PostgreSQL | [Bitnami Helm chart](https://github.com/bitnami/charts/tree/main/bitnami/postgresql) o servicio gestionado (RDS, Cloud SQL, Supabase) |
| Redis | [Bitnami Helm chart](https://github.com/bitnami/charts/tree/main/bitnami/redis) o ElastiCache / Upstash |

```bash
helm install sunat-postgres bitnami/postgresql \
  --namespace sunat \
  --set auth.database=sunat \
  --set auth.username=sunat \
  --set auth.password=<clave_segura>

helm install sunat-redis bitnami/redis \
  --namespace sunat \
  --set auth.enabled=false
```

#### 5. Autoescalado horizontal

```bash
kubectl autoscale deployment sunat-api \
  --namespace sunat \
  --cpu-percent=70 \
  --min=2 \
  --max=10

## Fase B — Integración Continua (CI) con GitHub Actions

### Objetivo

Automatizar verificación de calidad: construir la imagen de producción, validar que la aplicación arranca y ejecutar la suite de tests en un entorno reproducible (misma imagen Docker que se usará en producción).

### Principios

- Reproducibilidad: el CI debe construir la misma imagen que se desplegará en producción (`--target production`).
- Entorno único: ejecutar tests dentro de contenedores (o con servicios `postgres` y `redis`) para evitar "works on my machine".
- Seguridad: secretos (POSTGRES_PASSWORD, REGISTRY credentials) via `secrets` de GitHub.
- Feedback temprano: fallar el PR si lint/build/tests fallan.

### Flujo propuesto (stages)

1. Checkout del repo.
2. Linting y chequeos estáticos (`ruff`/`mypy`/`black` opcional).
3. Construir la imagen Docker de producción:
   - `docker build --target production -t ghcr.io/<org>/sunat-fastapi:${{ github.sha }} .`
   - Fallar si el build falla.
4. Levantar servicios necesarios (Postgres/Redis) como contenedores en el runner o usar `services` de Actions.
5. Ejecutar migraciones: `alembic upgrade head` contra la base creada por el servicio Postgres.
6. Ejecutar tests: `pytest -q`.
7. (Opcional en PRs) Ejecutar `docker run --rm -e DATABASE_URL=...` para verificar que la app arranca y responde `/health`.
8. En `main` (merge): taggear y pushear la imagen al registry (GHCR / Docker Hub) usando `GITHUB_TOKEN` o un `secret` con permisos.

### Secrets requeridos

- `POSTGRES_PASSWORD` (para los servicios de test)
- `GHCR_TOKEN` o `DOCKERHUB_USERNAME`/`DOCKERHUB_TOKEN` (para push de imagen)
- `DB_ADMIN_URL` (solo si usas un servicio externo en CI)

### Ejemplo mínimo de workflow: `.github/workflows/ci.yml`

```yaml
name: CI

on:
  push:
    branches: ["main"]
  pull_request:
    branches: ["main"]

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_USER: sunat
          POSTGRES_DB: sunat
          POSTGRES_PASSWORD: ${{ secrets.POSTGRES_PASSWORD }}
        ports: ["5432:5432"]
        options: >-
          --health-cmd="pg_isready -U sunat -d sunat" --health-interval=10s --health-timeout=5s --health-retries=5
      redis:
        image: redis:7-alpine
        ports: ["6379:6379"]

    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Cache Docker layers
        uses: actions/cache@v4
        with:
          path: /tmp/.buildx-cache
          key: ${{ runner.os }}-buildx-${{ github.sha }}

      - name: Build production image
        run: |
          docker build --target production -t sunat-fastapi:test .

      - name: Wait for Postgres
        run: |
          until pg_isready -h localhost -p 5432 -U sunat; do sleep 1; done
        env:
          PGPASSWORD: ${{ secrets.POSTGRES_PASSWORD }}

      - name: Install test deps
        run: |
          python -m pip install --upgrade pip
          pip install -r requirements.txt

      - name: Run migrations
        run: |
          alembic upgrade head

      - name: Run tests
        run: |
          pytest -q

  publish-image:
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    runs-on: ubuntu-latest
    needs: build-and-test
    steps:
      - uses: actions/checkout@v4
      - name: Log in to GitHub Container Registry
        uses: docker/login-action@v2
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GHCR_TOKEN }}
      - name: Build and push
        run: |
          docker build --target production -t ghcr.io/<org>/sunat-fastapi:${{ github.sha }} .
          docker push ghcr.io/<org>/sunat-fastapi:${{ github.sha }}
```

### Notas y recomendaciones

- Para repositorios públicos, GHCR permite almacenar imágenes gratuitamente (con límites). Protege los `secrets`.
- Los `services` de Actions son suficientes para tests de integración pequeños. Para suites pesadas, considera ejecutar un job que arranque `docker compose up -d` en el runner.
- Mantén los tests rápidos (< 5 min) para feedback inmediato en PRs.
- En PRs, evita push al registry — solo build + tests. Push ocurre en `main`.

### Resultado esperado

- PRs que fallen al no cumplir lint, build o tests.
- Build reproducible y probado antes de publicar la imagen.
- Historia de imágenes etiquetadas por commit SHA en el registry.

---

**Fin de Fase B**: una vez aprobado, el siguiente paso es crear el workflow en `.github/workflows/ci.yml`, añadir los `secrets` en GitHub y validar con un PR de prueba.
```