"""
Punto de entrada del servicio FastAPI para integración con SUNAT (Perú).

Inicializa la aplicación, registra routers y configura middleware.
"""
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.core.config import settings
from app.core.logger import configure_logging
from app.api.v1 import router as v1_router

# Configurar logging estructurado al iniciar
configure_logging()

app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    description="Puente FastAPI para envío de comprobantes electrónicos a SUNAT (Perú). "
                "Pipeline: recibir JSON → generar XML → firmar → enviar → retornar.",
    docs_url="/docs",
    redoc_url="/redoc",
)

# CORS — ajustar origins en producción
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/health", tags=["sistema"])
async def health_check():
    """Verificación rápida de disponibilidad del servicio."""
    return {"status": "ok", "version": settings.APP_VERSION}

# Registrar rutas v1
app.include_router(v1_router, prefix="/api/v1")


