"""
Rutas de documentos: pipeline completo de comprobantes electrónicos.
"""
import logging

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.auth import get_company_by_api_token
from app.core.db import get_db
from app.schemas.document import DocumentRecord, DocumentRequest, PipelineResponse
from app.services.document_store import get_document_by_id
from app.services.pipeline import run_document_pipeline

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/documents", tags=["documents"])


@router.post(
    "",
    response_model=PipelineResponse,
    status_code=status.HTTP_200_OK,
    summary="Procesar comprobante electrónico (pipeline completo)",
)
async def process_document(
    payload: DocumentRequest,
    db: Session = Depends(get_db),
    company: dict = Depends(get_company_by_api_token),
) -> PipelineResponse:
    """
    Recibe el JSON de un comprobante y ejecuta el pipeline completo:

    1. Validar X-API-Key y obtener la compañía autenticada.
    2. Generar XML unsigned (Jinja2).
    3. Firmar XML (local con python-xmlsec, o delegar al PSE).
    4. Enviar a SUNAT / OSE / PSE vía SOAP/REST.
    5. Parsear CDR y mapear código de respuesta.
    6. Si actions.persist=True, guardar estado en BD con el payload original.
    7. Retornar artefactos: xml_unsigned, xml_signed, cdr, hash, qr (base64).

    Tipos soportados: invoice, credit, debit, summary, voided,
                      retention, perception, dispatch, purchase_settlement.
    """
    try:
        result = await run_document_pipeline(payload, company, db=db)
    except Exception as exc:
        logger.exception(
            "Error en el pipeline de documentos ruc=%s type=%s series=%s",
            company["legal_number"], payload.type, payload.series,
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error en el pipeline: {exc}",
        ) from exc

    return result


@router.get(
    "/{document_id}",
    response_model=DocumentRecord,
    status_code=status.HTTP_200_OK,
    summary="Obtener estado y metadatos de un documento persistido",
)
async def get_document(
    document_id: int,
    db: Session = Depends(get_db),
    company: dict = Depends(get_company_by_api_token),
) -> DocumentRecord:
    """
    Retorna los metadatos del documento almacenado en BD.
    No incluye artefactos en base64; solo rutas, estado y códigos SUNAT.

    Solo accesible para la compañía propietaria del documento.
    """
    doc = get_document_by_id(db, document_id)
    if doc is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Documento con id={document_id} no encontrado.",
        )

    # Verificar que el documento pertenece a la compañía autenticada
    if doc["company_id"] != company["id"]:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="No tiene acceso al documento solicitado.",
        )

    return DocumentRecord(**doc)
