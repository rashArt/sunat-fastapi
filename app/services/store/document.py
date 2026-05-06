"""
Lógica de persistencia de documentos electrónicos.

Patrón equivalente a company_store.py pero para la tabla `document`.
No usa caché Redis — los documentos no son de lectura intensiva;
cada consulta va directamente a PostgreSQL.

Funciones principales:
  create_document      — INSERT con estado inicial "01" y payload original
  update_document_state — UPDATE progresivo según etapas del pipeline
  get_document_by_id   — lectura por PK
  get_document_by_filename  — consulta para idempotencia
  get_document_by_external_id — consulta por id del consumidor
  get_document_by_ticket — resolución de ticket asíncrono
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Optional

from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.models.document_model import Document
from app.schemas.document import DocumentRequest

logger = logging.getLogger(__name__)

# Estados terminales — no se re-procesa un documento en estado terminal
_TERMINAL_STATES = {"05", "07", "09"}


# ---------------------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------------------

def _document_to_dict(doc: Document) -> dict:
    """Convierte el ORM Document a diccionario serializable."""
    return {
        "id":                  doc.id,
        "company_id":          doc.company_id,
        "external_id":         doc.external_id,
        "filename":            doc.filename,
        "type":                doc.type,
        "document_type_id":    doc.document_type_id,
        "series":              doc.series,
        "number":              doc.number,
        "date_of_issue":       doc.date_of_issue.isoformat() if doc.date_of_issue else None,
        "state_type_id":       doc.state_type_id,
        "ticket":              doc.ticket,
        "sunat_code":          doc.sunat_code,
        "sunat_description":   doc.sunat_description,
        "sunat_notes":         doc.sunat_notes or [],
        "hash":                doc.hash,
        "xml_unsigned_path":   doc.xml_unsigned_path,
        "xml_signed_path":     doc.xml_signed_path,
        "cdr_path":            doc.cdr_path,
        "attempt_count":       doc.attempt_count,
        "processed_at":        doc.processed_at.isoformat() if doc.processed_at else None,
        "created_at":          doc.created_at.isoformat() if doc.created_at else None,
        "updated_at":          doc.updated_at.isoformat() if doc.updated_at else None,
    }


# ---------------------------------------------------------------------------
# Operaciones CRUD
# ---------------------------------------------------------------------------

def create_document(
    db: Session,
    company_id: int,
    doc: DocumentRequest,
    filename: str,
) -> Document:
    """
    Inserta un nuevo registro de documento con estado inicial "01" (Registrado).

    Guarda el payload JSON original completo para auditoría y validación
    futura contra el XML generado.

    Args:
        db:         Sesión SQLAlchemy activa.
        company_id: ID de la compañía emisora (FK).
        doc:        Datos del documento validados por Pydantic.
        filename:   Nombre de archivo SUNAT ({RUC}-{tipo}-{serie}-{numero}).

    Returns:
        Instancia Document persistida (con id asignado).

    Raises:
        IntegrityError si el filename ya existe para esa compañía
        (señal de que se debe usar get_document_by_filename antes de llamar esto).
    """
    date_of_issue = None
    if doc.date_of_issue:
        try:
            from datetime import date
            date_of_issue = date.fromisoformat(doc.date_of_issue)
        except (ValueError, AttributeError):
            pass

    new_doc = Document(
        company_id=company_id,
        external_id=getattr(doc, "external_id", None),
        filename=filename,
        type=doc.type.value,
        document_type_id=doc.document_type_id,
        series=doc.series,
        number=doc.number,
        date_of_issue=date_of_issue,
        state_type_id="01",
        request_payload=doc.model_dump(mode="json"),
    )
    db.add(new_doc)
    db.commit()
    db.refresh(new_doc)
    logger.debug("Documento registrado: id=%s filename=%s", new_doc.id, new_doc.filename)
    return new_doc


def update_document_state(
    db: Session,
    document_id: int,
    state_type_id: str,
    *,
    sunat_code: Optional[str] = None,
    sunat_description: Optional[str] = None,
    sunat_notes: Optional[list] = None,
    hash_value: Optional[str] = None,
    ticket: Optional[str] = None,
    xml_unsigned_path: Optional[str] = None,
    xml_signed_path: Optional[str] = None,
    cdr_path: Optional[str] = None,
    increment_attempt: bool = False,
    mark_processed: bool = False,
) -> Optional[Document]:
    """
    Actualiza el estado del documento y los campos opcionales provistos.

    Diseñado para ser llamado en múltiples puntos del pipeline:
      - Antes de enviar a SUNAT → state "03"
      - Después de recibir CDR → state "05"/"07"/"09" + sunat_code + cdr_path
      - Al resolver ticket → state "05"/"07"/"09" + sunat_code + cdr_path

    Solo actualiza campos que son distintos de None (no sobreescribe con None).
    """
    doc = db.query(Document).filter(Document.id == document_id).first()
    if doc is None:
        logger.warning("update_document_state: documento id=%s no encontrado", document_id)
        return None

    doc.state_type_id = state_type_id

    if sunat_code is not None:
        doc.sunat_code = sunat_code
    if sunat_description is not None:
        doc.sunat_description = sunat_description
    if sunat_notes is not None:
        doc.sunat_notes = sunat_notes
    if hash_value is not None:
        doc.hash = hash_value
    if ticket is not None:
        doc.ticket = ticket
    if xml_unsigned_path is not None:
        doc.xml_unsigned_path = xml_unsigned_path
    if xml_signed_path is not None:
        doc.xml_signed_path = xml_signed_path
    if cdr_path is not None:
        doc.cdr_path = cdr_path
    if increment_attempt:
        doc.attempt_count = (doc.attempt_count or 1) + 1
    if mark_processed:
        doc.processed_at = datetime.now(tz=timezone.utc)

    doc.updated_at = datetime.now(tz=timezone.utc)

    db.commit()
    db.refresh(doc)
    logger.debug(
        "Documento id=%s actualizado a estado=%s sunat_code=%s",
        document_id, state_type_id, sunat_code,
    )
    return doc


# ---------------------------------------------------------------------------
# Consultas
# ---------------------------------------------------------------------------

def get_document_by_id(db: Session, document_id: int) -> Optional[dict]:
    """Retorna el dict del documento por PK, o None si no existe."""
    doc = db.query(Document).filter(Document.id == document_id).first()
    return _document_to_dict(doc) if doc else None


def get_document_by_filename(
    db: Session,
    company_id: int,
    filename: str,
) -> Optional[Document]:
    """
    Busca un documento por compañía + filename.

    Usado en el pipeline para idempotencia:
    si ya existe, no se vuelve a emitir.
    """
    return (
        db.query(Document)
        .filter(Document.company_id == company_id, Document.filename == filename)
        .first()
    )


def get_document_by_external_id(
    db: Session,
    company_id: int,
    external_id: str,
) -> Optional[dict]:
    """Retorna el dict del documento por el id externo del consumidor."""
    doc = (
        db.query(Document)
        .filter(Document.company_id == company_id, Document.external_id == external_id)
        .first()
    )
    return _document_to_dict(doc) if doc else None


def get_document_by_ticket(
    db: Session,
    company_id: int,
    ticket: str,
) -> Optional[Document]:
    """
    Busca el documento que generó un ticket SUNAT dado.

    Usado en el endpoint de tickets para actualizar el estado
    cuando SUNAT procesa el resumen/baja asíncrono.
    """
    return (
        db.query(Document)
        .filter(Document.company_id == company_id, Document.ticket == ticket)
        .first()
    )
