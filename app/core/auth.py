"""
Dependencias de autenticación para endpoints protegidos.

Usa el header X-API-Key para identificar y validar la compañía.
El token es generado al registrar la compañía y nunca cambia sin rotación explícita.
"""
from typing import Optional

from fastapi import Depends, Header, HTTPException, status
from sqlalchemy.orm import Session

from app.core.db import get_db
from app.services.company_store import get_company_by_token


async def get_company_by_api_token(
    x_api_key: Optional[str] = Header(None, alias="X-API-Key"),
    db: Session = Depends(get_db),
) -> dict:
    """
    Dependency FastAPI que valida el header X-API-Key y retorna la compañía asociada.

    Flujo:
      - Header ausente → 401 Unauthorized
      - Token no corresponde a ninguna compañía → 404 Not Found
      - Compañía inactiva (status != 'active') → 403 Forbidden
      - OK → retorna el dict de configuración de la compañía

    Uso en endpoints:
        company: dict = Depends(get_company_by_api_token)
    """
    if not x_api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Se requiere el header X-API-Key para acceder a este recurso.",
            headers={"WWW-Authenticate": "ApiKey"},
        )

    company = get_company_by_token(db, x_api_key)
    if company is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Token de API inválido o compañía no encontrada.",
        )

    if company.get("status") != "active":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="La compañía asociada al token está inactiva. Contacte al administrador.",
        )

    return company
