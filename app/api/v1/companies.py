"""
Rutas de compañías: registro, actualización y consulta.
"""
import logging

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.db import get_db
from app.schemas.company import CompanyRegisterRequest, CompanyResponse
from app.services.store.company import store_company, get_company

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/companies", tags=["companies"])


@router.post(
    "",
    response_model=CompanyResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Registrar o actualizar compañía",
)
def register_company(
    payload: CompanyRegisterRequest,
    db: Session = Depends(get_db),
) -> CompanyResponse:
    """
    Registra una compañía con sus credenciales SUNAT y certificado digital.

    - Almacena el certificado de forma segura en el storage local o S3.
    - Persiste las credenciales cifradas para ser usadas en el pipeline.
    - Si la compañía ya existe (mismo RUC), actualiza sus datos.
    """
    # Detectar si la compañía ya existe para distinguir registro vs actualización
    existing = get_company(db, payload.legal_number)
    try:
        company_meta = store_company(db, payload)
        if existing is None:
            logger.info("Compañía registrada: %s", payload.legal_number)
            message = "Compañía registrada correctamente."
        else:
            logger.info("Compañía actualizada: %s", payload.legal_number)
            message = "Compañía actualizada correctamente."
    except ValueError as exc:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=str(exc),
        ) from exc
    except Exception as exc:
        logger.exception("Error al registrar compañía: %s", payload.legal_number)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error al registrar compañía: {exc}",
        ) from exc

    return CompanyResponse(
        id=company_meta.get("id"),
        legal_number=company_meta.get("legal_number", payload.legal_number),
        legal_name=company_meta.get("legal_name", payload.legal_name),
        trade_name=company_meta.get("trade_name", payload.trade_name),
        sunat_mode=company_meta.get("sunat_mode", payload.sunat_mode),
        soap_send_type=company_meta.get("soap_send_type", payload.soap_send_type),
        soap_username=company_meta.get("soap_username", payload.soap_username),
        soap_password=company_meta.get("soap_password", payload.soap_password),
        certificate_password=company_meta.get("certificate_password", payload.certificate_password),
        certificate_format=company_meta.get("certificate_format"),
        certificate_path=company_meta.get("certificate_path"),
        pse_provider=company_meta.get("pse_provider", payload.pse_provider),
        pse_username=company_meta.get("pse_username"),
        pse_password=company_meta.get("pse_password"),
        pse_token_url=company_meta.get("pse_token_url"),
        pse_generate_url=company_meta.get("pse_generate_url"),
        pse_send_url=company_meta.get("pse_send_url"),
        pse_query_url=company_meta.get("pse_query_url"),
        send_document_to_pse=company_meta.get("send_document_to_pse", payload.send_document_to_pse),
        response_mode=company_meta.get("response_mode", payload.response_mode),
        wait_seconds=company_meta.get("wait_seconds", payload.wait_seconds),
        auto_poll_ticket=company_meta.get("auto_poll_ticket", payload.auto_poll_ticket),
        ticket_wait_seconds=company_meta.get("ticket_wait_seconds", payload.ticket_wait_seconds),
        ticket_poll_interval_ms=company_meta.get("ticket_poll_interval_ms", payload.ticket_poll_interval_ms),
        api_token=company_meta.get("api_token", payload.api_token),
        status=company_meta.get("status", payload.status),
        created_at=company_meta.get("created_at"),
        updated_at=company_meta.get("updated_at"),
        message=message,
    )


@router.get(
    "/{legal_number}",
    response_model=CompanyResponse,
    status_code=status.HTTP_200_OK,
    summary="Obtener datos completos de una compañía",
)
def get_company_detail(
    legal_number: str,
    db: Session = Depends(get_db),
) -> CompanyResponse:
    """Retorna la configuración completa de la compañía (desde BD o caché Redis)."""
    company = get_company(db, legal_number)
    if company is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Compañía con RUC '{legal_number}' no encontrada.",
        )

    return CompanyResponse(
        id=company.get("id"),
        legal_number=company.get("legal_number"),
        legal_name=company.get("legal_name"),
        trade_name=company.get("trade_name"),
        sunat_mode=company.get("sunat_mode"),
        soap_send_type=company.get("soap_send_type"),
        soap_username=company.get("soap_username"),
        soap_password=company.get("soap_password"),
        certificate_password=company.get("certificate_password"),
        certificate_format=company.get("certificate_format"),
        certificate_path=company.get("certificate_path"),
        pse_provider=company.get("pse_provider"),
        pse_username=company.get("pse_username"),
        pse_password=company.get("pse_password"),
        pse_token_url=company.get("pse_token_url"),
        pse_generate_url=company.get("pse_generate_url"),
        pse_send_url=company.get("pse_send_url"),
        pse_query_url=company.get("pse_query_url"),
        send_document_to_pse=company.get("send_document_to_pse"),
        response_mode=company.get("response_mode"),
        wait_seconds=company.get("wait_seconds"),
        auto_poll_ticket=company.get("auto_poll_ticket"),
        ticket_wait_seconds=company.get("ticket_wait_seconds"),
        ticket_poll_interval_ms=company.get("ticket_poll_interval_ms"),
        api_token=company.get("api_token"),
        status=company.get("status"),
        created_at=company.get("created_at"),
        updated_at=company.get("updated_at"),
        message="Compañía encontrada.",
    )

