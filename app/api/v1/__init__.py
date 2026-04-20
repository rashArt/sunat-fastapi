"""
Paquete api.v1 — agrega todos los routers del dominio.
"""
from fastapi import APIRouter

from app.api.v1.companies import router as companies_router
from app.api.v1.documents import router as documents_router
from app.api.v1.tickets import router as tickets_router

router = APIRouter()

router.include_router(companies_router)
router.include_router(documents_router)
router.include_router(tickets_router)

