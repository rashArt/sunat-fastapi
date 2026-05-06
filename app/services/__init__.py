"""
Paquete services: orquestación de documentos, persistencia y comunicación con SUNAT.

Estructura:
  - document/   → Generación XML, firma digital y QR
  - store/      → Persistencia en BD (compañías y documentos)
  - pipeline.py → Orquestador principal del flujo de comprobantes
  - sender.py   → Clientes SOAP/REST para SUNAT, OSE, PSE
  - storage.py  → Persistencia de archivos en filesystem/S3
"""
from app.services.pipeline import run_document_pipeline
from app.services.store import (
    store_company,
    get_company_by_token,
    get_company,
    create_document,
    update_document_state,
    get_document_by_id,
    get_document_by_filename,
    get_document_by_external_id,
    get_document_by_ticket,
)

__all__ = [
    "run_document_pipeline",
    "store_company",
    "get_company_by_token",
    "get_company",
    "create_document",
    "update_document_state",
    "get_document_by_id",
    "get_document_by_filename",
    "get_document_by_external_id",
    "get_document_by_ticket",
]
