"""
Rutas de tickets: consulta de estado para resúmenes diarios y bajas.
"""
import base64
import logging
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session

from app.core.auth import get_company_by_api_token
from app.core.db import get_db
from app.schemas.document import TicketStatusResponse
from app.services import document_store
from app.services.sender import build_sunat_sender
from app.services.storage import StorageService

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/tickets", tags=["tickets"])


def _resolver_estado_ticket(code: Optional[str]) -> tuple[str, str]:
    """
    Mapea el código de respuesta de getStatus a estado interno.

    Códigos especiales:
      98 → SUNAT aún no procesó el ticket (estado 03 = En proceso)
      0  → Aceptado (05)
      99 → Aceptado (05)
      2xxx-3xxx → Observado con notas (07)
      otros → Rechazado (09)
    """
    if code == "98":
        return "03", "Ticket pendiente de procesamiento por SUNAT"
    if code is None:
        return "09", "Sin respuesta de SUNAT"
    try:
        code_int = int(code)
    except (ValueError, TypeError):
        return "09", f"Código de respuesta inesperado: {code}"
    if code_int in (0, 99):
        return "05", "Aceptado"
    if code_int in range(2000, 4000):
        return "07", f"Aceptado con observaciones (código {code})"
    return "09", f"Rechazado por SUNAT (código {code})"


@router.get(
    "/{ticket}",
    response_model=TicketStatusResponse,
    status_code=status.HTTP_200_OK,
    summary="Consultar estado de ticket de documento asíncrono",
)
async def query_ticket(
    ticket: str,
    db: Session = Depends(get_db),
    company: dict = Depends(get_company_by_api_token),
) -> TicketStatusResponse:
    """
    Consulta el estado de un ticket emitido por SUNAT para resúmenes diarios (RC)
    o comunicaciones de baja (RA).

    SUNAT no procesa estos documentos de inmediato; si la respuesta devuelve
    state_type_id='03' (En proceso) esperar ~1 minuto y volver a consultar.

    Si el ticket fue procesado, la respuesta incluye el CDR en cdr_b64.
    Cuando el documento fue persistido (persist=True), actualiza su estado en BD.
    """
    try:
        # Los resúmenes/bajas usan el WSDL 'fe' (billService) para getStatus
        sender = build_sunat_sender("summary", company)
        result = sender.get_status(ticket)
    except Exception as exc:
        logger.exception("Error consultando ticket SUNAT: %s", ticket)
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Error conectando con SUNAT: {exc}",
        ) from exc

    state_type_id, state_description = _resolver_estado_ticket(result.code)
    company_id = company["id"]
    company_legal_number = company["legal_number"]

    # Persistir CDR en storage si ya está disponible
    cdr_local_path: Optional[str] = None
    if result.cdr_b64:
        storage = StorageService(company_legal_number)
        try:
            cdr_filename = f"ticket-{ticket}-CDR.zip"
            storage.save("cdr", cdr_filename, base64.b64decode(result.cdr_b64))
            cdr_local_path = str(storage.get_path("cdr", cdr_filename))
        except Exception as exc:
            logger.warning("No se pudo guardar el CDR del ticket %s: %s", ticket, exc)

    # Actualizar estado del documento en BD si existe un registro vinculado al ticket
    doc_record = document_store.get_document_by_ticket(db, company_id, ticket)
    if doc_record is not None and state_type_id != "03":
        # Solo actualizar si el ticket ya tiene resolución (no "En proceso")
        document_store.update_document_state(
            db,
            doc_record.id,
            state_type_id,
            sunat_code=result.code,
            sunat_description=result.description,
            sunat_notes=result.notes if result.notes else None,
            cdr_path=cdr_local_path,
            mark_processed=True,
        )

    return TicketStatusResponse(
        ticket=ticket,
        success=result.success,
        state_type_id=state_type_id,
        state_description=state_description,
        sunat_code=result.code,
        sunat_description=result.description,
        sunat_notes=result.notes,
        cdr_b64=result.cdr_b64,
    )
