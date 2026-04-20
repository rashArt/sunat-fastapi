from sqlalchemy import (
    Column,
    Integer,
    String,
    Boolean,
    DateTime,
    func,
)
from sqlalchemy.orm import relationship

from app.core.db import Base


class Company(Base):
    __tablename__ = "company"

    id = Column(Integer, primary_key=True, index=True)
    legal_number = Column(String(11), unique=True, nullable=False, index=True)
    legal_name = Column(String(250), nullable=False)
    trade_name = Column(String(250), nullable=True)

    sunat_mode = Column(String(10), nullable=False)
    soap_send_type = Column(String(10), nullable=False)

    soap_username = Column(String(200), nullable=True)
    soap_password = Column(String(500), nullable=True)

    certificate_password = Column(String(200), nullable=True)
    certificate_format = Column(String(10), nullable=True)
    certificate_path = Column(String(1000), nullable=True)

    pse_provider = Column(String(50), nullable=True)
    pse_username = Column(String(200), nullable=True)
    pse_password = Column(String(500), nullable=True)
    pse_token_url = Column(String(1000), nullable=True)
    pse_generate_url = Column(String(1000), nullable=True)
    pse_send_url = Column(String(1000), nullable=True)
    pse_query_url = Column(String(1000), nullable=True)

    send_document_to_pse = Column(Boolean, default=False)

    # Phase1 operational
    response_mode = Column(String(20), default="hybrid")
    wait_seconds = Column(Integer, default=10)
    auto_poll_ticket = Column(Boolean, default=False)
    ticket_wait_seconds = Column(Integer, default=60)
    ticket_poll_interval_ms = Column(Integer, default=1000)
    api_token = Column(String(500), nullable=True)
    status = Column(String(20), default="active")

    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

    # Relación inversa con documentos
    documents = relationship("Document", back_populates="company", lazy="dynamic")
