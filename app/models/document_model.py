"""
Modelo ORM para la tabla `document`.

Registra cada comprobante electrónico procesado por el pipeline,
incluyendo su estado SUNAT, artefactos generados y el payload JSON
original para validación futura contra el XML.
"""
from sqlalchemy import (
    Boolean,
    Column,
    Date,
    DateTime,
    ForeignKey,
    Index,
    Integer,
    String,
    Text,
    UniqueConstraint,
    func,
)
from sqlalchemy.dialects.postgresql import JSON
from sqlalchemy.orm import relationship

from app.core.db import Base


class Document(Base):
    __tablename__ = "document"

    # ------------------------------------------------------------------
    # Identificación
    # ------------------------------------------------------------------
    id = Column(Integer, primary_key=True, autoincrement=True)

    # FK a la compañía emisora; cascada sólo lectura (no borrado)
    company_id = Column(Integer, ForeignKey("company.id", ondelete="RESTRICT"), nullable=False)

    # Identificador externo del consumidor (para idempotencia Phase 3)
    external_id = Column(String(100), nullable=True)

    # Nombre de archivo SUNAT: {RUC}-{doc_type_id}-{serie}-{numero}
    filename = Column(String(100), nullable=False)

    # ------------------------------------------------------------------
    # Tipo de documento
    # ------------------------------------------------------------------
    type = Column(String(40), nullable=False)           # invoice / credit / voided / etc.
    document_type_id = Column(String(2), nullable=False)  # 01 / 03 / 07 / 08 / ...
    series = Column(String(10), nullable=False)
    number = Column(String(10), nullable=False)
    date_of_issue = Column(Date, nullable=True)

    # ------------------------------------------------------------------
    # Estado del pipeline
    # ------------------------------------------------------------------
    # 01=Registrado, 03=Enviado, 05=Aceptado, 07=Observado, 09=Rechazado
    state_type_id = Column(String(2), nullable=False, server_default="01")

    # Ticket SUNAT para resúmenes/bajas asíncronos
    ticket = Column(String(50), nullable=True)

    # ------------------------------------------------------------------
    # Respuesta SUNAT
    # ------------------------------------------------------------------
    sunat_code = Column(String(10), nullable=True)
    sunat_description = Column(Text, nullable=True)
    sunat_notes = Column(JSON, nullable=True)   # lista de strings

    # ------------------------------------------------------------------
    # Artefactos (rutas en filesystem)
    # ------------------------------------------------------------------
    hash = Column(String(100), nullable=True)
    xml_unsigned_path = Column(String(300), nullable=True)
    xml_signed_path = Column(String(300), nullable=True)
    cdr_path = Column(String(300), nullable=True)

    # ------------------------------------------------------------------
    # Payload original
    # ------------------------------------------------------------------
    # JSON completo recibido por el endpoint. Permite:
    #   - Validación futura contra el XML generado
    #   - Replay del pipeline con los mismos datos
    #   - Auditoria del comprobante
    request_payload = Column(JSON, nullable=True)

    # ------------------------------------------------------------------
    # Control
    # ------------------------------------------------------------------
    attempt_count = Column(Integer, nullable=False, server_default="1")
    processed_at = Column(DateTime(timezone=True), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), nullable=True, onupdate=func.now())

    # ------------------------------------------------------------------
    # Relaciones
    # ------------------------------------------------------------------
    company = relationship("Company", back_populates="documents", lazy="select")

    # ------------------------------------------------------------------
    # Constraints e índices
    # ------------------------------------------------------------------
    __table_args__ = (
        # Idempotencia: un filename es único por compañía
        UniqueConstraint("company_id", "filename", name="uq_document_company_filename"),
        # Búsqueda rápida por external_id del consumidor
        Index("ix_document_company_external", "company_id", "external_id"),
        # Consultas de estado (monitoreo / soporte)
        Index("ix_document_state", "state_type_id"),
        # Resolución de tickets asíncronos
        Index("ix_document_company_ticket", "company_id", "ticket"),
    )

    def __repr__(self) -> str:
        return (
            f"<Document id={self.id} filename={self.filename!r} "
            f"state={self.state_type_id!r}>"
        )
