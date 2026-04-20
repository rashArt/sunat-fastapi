"""
Schemas Pydantic para registro y actualización de compañía.

Una compañía encapsula las credenciales SUNAT/OSE/PSE y el certificado
digital necesario para firmar y enviar comprobantes electrónicos.
"""
from __future__ import annotations

from enum import Enum
from typing import Optional
import datetime

from pydantic import BaseModel, Field, field_validator


class SunatMode(str, Enum):
    """Modo de operación SUNAT."""
    demo = "demo"
    prod = "prod"


class SoapSendType(str, Enum):
    """
    Equivalente a soap_send_id en CoreFacturalo.
    - sunat: envío directo a SUNAT
    - ose:   envío vía Operador de Servicios Electrónicos
    - pse:   envío vía Proveedor de Servicios Electrónicos
    """
    sunat = "sunat"
    ose = "ose"
    pse = "pse"


class PseProvider(str, Enum):
    """
    Proveedor PSE/OSE REST soportado.

    - gior:      Gior Technology  (PSE + OSE)
    - qpse:      QPSE
    - sendfact:  SENDFact
    - validapse: VALIDAPSE
    - custom:    Proveedor personalizado (requiere pse_token_url y pse_send_url)

    Para agregar un nuevo proveedor: agregar su slug al enum y su entrada
    en _PSE_PROVIDERS en app/services/sender.py.
    """
    gior      = "gior"
    qpse      = "qpse"
    sendfact  = "sendfact"
    validapse = "validapse"
    custom    = "custom"


class CompanyRegisterRequest(BaseModel):
    """
    Payload para registrar o actualizar una compañía.

    El certificado debe enviarse en base64 (formato .p12 o .pem).
    Las credenciales se almacenan de forma segura en el servidor.
    """

    # Identificación
    legal_number: str = Field(..., min_length=11, max_length=11, pattern=r"^\d{11}$", description="RUC de la empresa (11 dígitos)")
    legal_name: str = Field(..., max_length=250, description="Razón social")
    trade_name: Optional[str] = Field(None, max_length=250, description="Nombre comercial")

    # Entorno y modo de envío
    sunat_mode: SunatMode = Field(SunatMode.demo, description="'demo' = beta SUNAT, 'prod' = producción")
    soap_send_type: SoapSendType = Field(SoapSendType.sunat, description="Canal de envío: sunat | ose | pse")

    # Credenciales SOL para SUNAT / OSE
    soap_username: str = Field(..., description="Usuario SOL (RUC + SUNAT_USER o usuario OSE)")
    soap_password: str = Field(..., description="Clave SOL")

    # Certificado digital
    certificate_b64: str = Field(..., description="Certificado .p12 o .pem codificado en base64")
    certificate_password: Optional[str] = Field(None, description="Contraseña del .p12 (si aplica)")

    # PSE / OSE opcionales
    pse_provider: Optional[PseProvider] = Field(
        None,
        description=(
            "Proveedor PSE/OSE: gior | qpse | sendfact | validapse | custom. "
            "Requerido cuando soap_send_type es 'pse' u 'ose'."
        ),
    )
    pse_username: Optional[str] = Field(None, description="Usuario/credencial del proveedor PSE")
    pse_password: Optional[str] = Field(None, description="Contraseña del proveedor PSE")

    # Endpoints para proveedor 'custom' (ignorados si pse_provider != 'custom')
    pse_token_url:    Optional[str] = Field(None, description="URL del endpoint de autenticación del proveedor custom")
    pse_generate_url: Optional[str] = Field(None, description="URL del endpoint de firma (solo PSE custom)")
    pse_send_url:     Optional[str] = Field(None, description="URL del endpoint de envío del proveedor custom")
    pse_query_url:    Optional[str] = Field(None, description="URL del endpoint de consulta del proveedor custom")

    # Configuración adicional
    send_document_to_pse: bool = Field(False, description="True = firma y envío vía PSE, False = local + SUNAT directo")

    # Comportamiento operacional / pipeline (Fase 1 del roadmap)
    response_mode: str = Field("hybrid", description="Modo de respuesta: 'hybrid' (default) | 'sync' | 'async'")
    wait_seconds: int = Field(10, description="Segundos de espera para intento síncrono antes de fallback")
    auto_poll_ticket: bool = Field(False, description="Si True, el servidor consultará automáticamente tickets async dentro de la ventana")
    ticket_wait_seconds: int = Field(60, description="Segundos totales para esperar la resolución del ticket cuando auto_poll_ticket=True")
    ticket_poll_interval_ms: int = Field(1000, description="Intervalo en ms entre consultas de ticket durante polling")
    api_token: Optional[str] = Field(None, description="Token opcional por empresa para autenticación simple (X-API-Key)")
    status: str = Field("active", description="Estado de la compañía: 'active' | 'inactive'")

    @field_validator("legal_number")
    @classmethod
    def legal_number_must_be_numeric(cls, v: str) -> str:
        if not v.isdigit():
            raise ValueError("El legal_number debe contener solo dígitos.")
        return v

    @field_validator("pse_provider")
    @classmethod
    def pse_provider_required_when_pse_ose(cls, v, info):
        send_type = info.data.get("soap_send_type")
        if send_type in (SoapSendType.pse, SoapSendType.ose) and v is None:
            raise ValueError(
                "pse_provider es requerido cuando soap_send_type es 'pse' u 'ose'."
            )
        return v


class CompanyResponse(BaseModel):
    """Respuesta tras registrar o actualizar una compañía."""
    # Identificación
    id: Optional[int] = None
    legal_number: str
    legal_name: str
    trade_name: Optional[str] = None

    # Modo y canal
    sunat_mode: SunatMode
    soap_send_type: SoapSendType

    # Credenciales y certificado
    soap_username: Optional[str] = None
    soap_password: Optional[str] = None
    certificate_password: Optional[str] = None
    certificate_format: Optional[str] = None
    certificate_path: Optional[str] = None

    # PSE / OSE
    pse_provider: Optional[PseProvider] = None
    pse_username: Optional[str] = None
    pse_password: Optional[str] = None
    pse_token_url: Optional[str] = None
    pse_generate_url: Optional[str] = None
    pse_send_url: Optional[str] = None
    pse_query_url: Optional[str] = None
    send_document_to_pse: bool = False

    # Comportamiento operativo
    response_mode: str = "hybrid"
    wait_seconds: int = 10
    auto_poll_ticket: bool = False
    ticket_wait_seconds: int = 60
    ticket_poll_interval_ms: int = 1000
    api_token: Optional[str] = None
    status: str = "active"

    # Tiempos
    created_at: Optional[datetime.datetime] = None
    updated_at: Optional[datetime.datetime] = None

    # Mensaje informativo
    message: str = "Compañía registrada correctamente."
