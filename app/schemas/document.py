"""
Schemas Pydantic para documentos electrónicos SUNAT.

Cubre los 7 tipos principales:
  invoice       → Factura / Boleta (01/03)
  credit        → Nota de crédito (07)
  debit         → Nota de débito (08)
  summary       → Resumen diario (RC)
  voided        → Anulación (RA)
  retention     → Retención (20)
  perception    → Percepción (40)
  dispatch      → Guía de remisión (09/31)
  purchase_settlement → Liquidación de compra (40)

Los decimales usan Decimal para evitar errores de redondeo en los totales SUNAT.
"""
from __future__ import annotations

from decimal import Decimal
from enum import Enum
from typing import Any, Dict, List, Optional

from pydantic import BaseModel, Field, field_validator, model_validator


# ---------------------------------------------------------------------------
# Catálogos / enumeraciones básicas
# ---------------------------------------------------------------------------

class DocumentType(str, Enum):
    invoice = "invoice"          # Factura (01) / Boleta (03)
    credit = "credit"            # Nota de crédito (07)
    debit = "debit"              # Nota de débito (08)
    summary = "summary"          # Resumen diario (RC)
    voided = "voided"            # Comunicación de baja (RA)
    retention = "retention"      # Retención (20)
    perception = "perception"    # Percepción (40)
    dispatch = "dispatch"        # Guía de remisión (09/31)
    purchase_settlement = "purchase_settlement"

    def __str__(self) -> str:
        # Python 3.10 devuelve "ClassName.member"; necesitamos solo el valor
        return self.value


class CurrencyType(str, Enum):
    pen = "PEN"
    usd = "USD"
    eur = "EUR"

    def __str__(self) -> str:
        # Python 3.10 devuelve "ClassName.member"; necesitamos solo el valor
        return self.value


# ---------------------------------------------------------------------------
# Sub-modelos de personas (emisor / receptor)
# ---------------------------------------------------------------------------

class PersonSchema(BaseModel):
    """
    Datos del cliente / receptor del comprobante.

    identity_document_type_id:
      '1' = DNI, '4' = Carnet de extranjería, '6' = RUC, '7' = Pasaporte, etc.
    """
    identity_document_type_id: str = Field(..., max_length=1, description="Código de tipo de documento de identidad")
    number: str = Field(..., max_length=15, description="Número de documento de identidad")
    name: str = Field(..., max_length=250, description="Nombre o razón social")
    address: Optional[str] = Field(None, max_length=300)
    email: Optional[str] = Field(None, max_length=200)
    phone: Optional[str] = Field(None, max_length=50)


# ---------------------------------------------------------------------------
# Items / líneas del documento
# ---------------------------------------------------------------------------

class ItemDiscountCharge(BaseModel):
    """Descuento o cargo aplicado a un ítem."""
    factor: Optional[Decimal] = Field(None, ge=0)
    amount: Decimal = Field(..., ge=0)
    base: Optional[Decimal] = Field(None, ge=0)
    type: Optional[str] = None  # código catálogo SUNAT


class ItemSchema(BaseModel):
    """
    Línea de detalle del comprobante.

    affectation_igv_type_id:
      '10'=Gravado, '20'=Exonerado, '30'=Inafecto, '40'=Exportación, etc.
    """
    item_id: Optional[int] = None
    description: str = Field(..., max_length=250)
    internal_id: Optional[str] = Field(None, max_length=50)
    unit_type_id: str = Field("NIU", max_length=10, description="Unidad de medida (catálogo UBL/SUNAT)")
    quantity: Decimal = Field(..., gt=0)
    unit_value: Decimal = Field(..., ge=0, description="Valor unitario sin IGV")
    unit_price: Decimal = Field(..., ge=0, description="Precio unitario con IGV")
    price_type_id: str = Field("01", max_length=2, description="01=precio unitario, 02=precio unitario con IGV")
    affectation_igv_type_id: str = Field("10", max_length=2)
    total_base_igv: Decimal = Field(..., ge=0)
    percentage_igv: Decimal = Field(Decimal("18.00"), ge=0)
    total_igv: Decimal = Field(..., ge=0)
    total_base_isc: Decimal = Field(Decimal("0.00"), ge=0)
    percentage_isc: Decimal = Field(Decimal("0.00"), ge=0)
    total_isc: Decimal = Field(Decimal("0.00"), ge=0)
    total_base_other_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    percentage_other_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_other_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_plastic_bag_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_taxes: Decimal = Field(..., ge=0)
    total_value: Decimal = Field(..., ge=0)
    total_charge: Decimal = Field(Decimal("0.00"), ge=0)
    total_discount: Decimal = Field(Decimal("0.00"), ge=0)
    total: Decimal = Field(..., ge=0)
    discounts: List[ItemDiscountCharge] = Field(default_factory=list)
    charges: List[ItemDiscountCharge] = Field(default_factory=list)
    additional_information: Optional[str] = Field(None, max_length=500)
    additional_data: Optional[Dict[str, Any]] = None


# ---------------------------------------------------------------------------
# Totales del documento
# ---------------------------------------------------------------------------

class TotalsSchema(BaseModel):
    """Totales del comprobante. Todos los montos en la moneda del documento."""
    total_exportation: Decimal = Field(Decimal("0.00"), ge=0)
    total_taxed: Decimal = Field(Decimal("0.00"), ge=0)
    total_unaffected: Decimal = Field(Decimal("0.00"), ge=0)
    total_exonerated: Decimal = Field(Decimal("0.00"), ge=0)
    total_free: Decimal = Field(Decimal("0.00"), ge=0)
    total_igv: Decimal = Field(..., ge=0)
    total_base_isc: Decimal = Field(Decimal("0.00"), ge=0)
    total_isc: Decimal = Field(Decimal("0.00"), ge=0)
    total_base_other_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_other_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_plastic_bag_taxes: Decimal = Field(Decimal("0.00"), ge=0)
    total_taxes: Decimal = Field(..., ge=0)
    total_value: Decimal = Field(..., ge=0)
    total_discount: Decimal = Field(Decimal("0.00"), ge=0)
    total_charge: Decimal = Field(Decimal("0.00"), ge=0)
    total_prepayment: Decimal = Field(Decimal("0.00"), ge=0)
    subtotal: Optional[Decimal] = Field(None, ge=0)
    total: Decimal = Field(..., ge=0)


# ---------------------------------------------------------------------------
# Datos de factura / boleta
# ---------------------------------------------------------------------------

class InvoiceSchema(BaseModel):
    """Campos exclusivos de facturas y boletas."""
    operation_type_id: str = Field("0101", max_length=4, description="Tipo de operación (catálogo 51)")
    date_of_due: Optional[str] = Field(None, description="Fecha de vencimiento YYYY-MM-DD")
    spot: Optional[Decimal] = Field(None, ge=0, description="Monto detracción")
    percent: Optional[Decimal] = Field(None, ge=0, description="% detracción")


# ---------------------------------------------------------------------------
# Notas de crédito / débito
# ---------------------------------------------------------------------------

class NoteSchema(BaseModel):
    """Campos de nota de crédito (07) o débito (08)."""
    note_type_id: str = Field(..., max_length=2, description="Tipo de nota (catálogo 9 para crédito / 10 para débito)")
    note_type_description: str = Field(..., max_length=250)
    document_type_id: str = Field(..., max_length=2, description="Tipo del documento referenciado")
    series: str = Field(..., max_length=4)
    number: str = Field(..., max_length=8)


# ---------------------------------------------------------------------------
# Acciones (equivalente a ActionInput en PHP)
# ---------------------------------------------------------------------------

class ActionsSchema(BaseModel):
    """Acciones a ejecutar en el pipeline."""
    send_xml_signed: bool = Field(True, description="Enviar XML firmado a SUNAT/OSE/PSE")
    send_email: bool = Field(False, description="Enviar email al cliente")
    format_pdf: bool = Field(False, description="Generar PDF (retornado en base64)")
    persist: bool = Field(False, description="Persistir documento en base de datos local")


# ---------------------------------------------------------------------------
# Metadata / establecimiento
# ---------------------------------------------------------------------------

class EstablishmentSchema(BaseModel):
    """Datos del establecimiento emisor."""
    address: str = Field(..., max_length=300)
    department: Optional[str] = Field(None, max_length=100)
    province: Optional[str] = Field(None, max_length=100)
    district: Optional[str] = Field(None, max_length=100)
    ubigeo: Optional[str] = Field(None, max_length=6)
    country_id: str = Field("PE", max_length=2)


# ---------------------------------------------------------------------------
# Documento referenciado en resúmenes / anulaciones
# ---------------------------------------------------------------------------

class RelatedDocumentSchema(BaseModel):
    """Documento incluido en resumen diario (RC) o baja (RA)."""
    document_type_id: str = Field(..., max_length=2)
    series: str = Field(..., max_length=4)
    number: str = Field(..., max_length=8)
    customer_identity_document_type_id: Optional[str] = Field(None, max_length=1)
    customer_number: Optional[str] = Field(None, max_length=15)
    total_taxed: Decimal = Field(Decimal("0.00"), ge=0)
    total_igv: Decimal = Field(Decimal("0.00"), ge=0)
    total: Decimal = Field(Decimal("0.00"), ge=0)
    state_type_id: Optional[str] = Field(None, max_length=2)
    description: Optional[str] = None  # motivo de baja


# ---------------------------------------------------------------------------
# Schema principal del documento
# ---------------------------------------------------------------------------

class DocumentRequest(BaseModel):
    """
    Payload principal del pipeline de comprobantes.

    El consumidor envía este JSON a POST /api/v1/documents.
    La compañía emisora se identifica mediante el header X-API-Key.
    El servicio ejecuta internamente:
      1. validar → 2. generar XML → 3. firmar → 4. enviar →
      5. parsear CDR → 6. retornar artefactos
    """

    # Tipo y serie
    type: DocumentType
    document_type_id: str = Field(..., max_length=2,
                                  description="Código SUNAT: 01=Factura, 03=Boleta, 07=NC, 08=ND, etc.")
    series: str = Field(..., max_length=4,
                        description="Serie: F001/B001 (factura/boleta), RC/RA (resúmenes), etc.")
    number: str = Field(..., max_length=8, description="Número correlativo")
    filename: Optional[str] = Field(None, max_length=50,
                                    description="Nombre del archivo sin extensión. Si se omite se genera automáticamente.")

    # Fechas
    date_of_issue: str = Field(..., description="Fecha de emisión YYYY-MM-DD")
    time_of_issue: str = Field("00:00:00", description="Hora de emisión HH:MM:SS")
    reference_date: Optional[str] = Field(None, description="Fecha de referencia (resúmenes)")

    # Establecimiento
    establishment: EstablishmentSchema

    # Receptor
    customer: Optional[PersonSchema] = None  # None en resúmenes/bajas

    # Moneda
    currency_type_id: CurrencyType = Field(CurrencyType.pen)
    exchange_rate_sale: Decimal = Field(Decimal("1.00"), ge=0)

    # Campos exclusivos por tipo
    invoice: Optional[InvoiceSchema] = None
    note: Optional[NoteSchema] = None

    # Ítems (facturas, boletas, NC, ND, liquidaciones)
    items: Optional[List[ItemSchema]] = None

    # Documentos incluidos (resúmenes / bajas)
    documents: Optional[List[RelatedDocumentSchema]] = None

    # Totales
    totals: Optional[TotalsSchema] = None

    # Condición de pago (catálogo SUNAT: '01'=Contado, '02'=Crédito)
    payment_condition_id: Optional[str] = Field(
        None, max_length=2,
        description="'01'=Contado, '02'=Crédito. Obligatorio desde RS 193-2020/SUNAT"
    )

    # Información adicional
    legends: Optional[List[Dict[str, str]]] = Field(
        None, description="[{'code': '1000', 'value': 'Son ciento dieciocho...'}]"
    )
    additional_information: Optional[str] = Field(None, max_length=500)
    purchase_order: Optional[str] = Field(None, max_length=100)

    # Acciones
    actions: ActionsSchema = Field(default_factory=ActionsSchema)

    # Identificador externo del consumidor (idempotencia Phase 3)
    # Valor libre que el consumidor asigna a su documento; el sistema
    # garantiza que (company, external_id) es único en BD.
    external_id: Optional[str] = Field(
        None,
        max_length=100,
        description="ID del documento en el sistema del consumidor. "
                    "Usado para idempotencia y trazabilidad cruzada.",
    )

    # Datos extra de paso a paso (proveedor externo puede enviar xml_signed ya firmado)
    xml_signed_b64: Optional[str] = Field(
        None, description="XML ya firmado por PSE externo (base64). Si se envía, se omite la firma local."
    )

    @field_validator("date_of_issue", "time_of_issue", "reference_date", mode="before")
    @classmethod
    def validar_no_vacio(cls, v: Any) -> Any:
        if v == "":
            return None
        return v

    @model_validator(mode="after")
    def validar_coherencia(self) -> "DocumentRequest":
        """Validaciones cruzadas básicas entre campos del documento."""
        if self.type in (DocumentType.invoice, DocumentType.credit, DocumentType.debit):
            if not self.customer:
                raise ValueError(f"El campo 'customer' es obligatorio para el tipo '{self.type}'.")
            if not self.items:
                raise ValueError(f"El campo 'items' es obligatorio para el tipo '{self.type}'.")
            if not self.totals:
                raise ValueError(f"El campo 'totals' es obligatorio para el tipo '{self.type}'.")

        if self.type in (DocumentType.summary, DocumentType.voided):
            if not self.documents:
                raise ValueError(f"El campo 'documents' es obligatorio para el tipo '{self.type}'.")

        if self.type == DocumentType.credit and not self.note:
            raise ValueError("El campo 'note' es obligatorio para notas de crédito.")

        if self.type == DocumentType.debit and not self.note:
            raise ValueError("El campo 'note' es obligatorio para notas de débito.")

        return self


# ---------------------------------------------------------------------------
# Respuesta del pipeline
# ---------------------------------------------------------------------------

class PipelineResponse(BaseModel):
    """
    Respuesta completa del pipeline de envío.

    Todos los artefactos XML / CDR se entregan en base64 para que el
    consumidor los almacene o genere PDF directamente.
    """
    success: bool
    filename: str
    state_type_id: str = Field(description="01=Registrado, 03=Enviado, 05=Aceptado, 07=Observado, 09=Rechazado")
    state_description: str

    # Códigos SUNAT
    sunat_code: Optional[str] = None
    sunat_description: Optional[str] = None
    sunat_notes: List[str] = Field(default_factory=list)

    # Artefactos
    xml_unsigned_b64: Optional[str] = None
    xml_signed_b64: Optional[str] = None
    cdr_b64: Optional[str] = None
    hash: Optional[str] = None
    qr_b64: Optional[str] = None
    pdf_b64: Optional[str] = None

    # Trazabilidad
    errors: List[str] = Field(default_factory=list)

    # ID del registro en BD (disponible cuando actions.persist=True)
    document_id: Optional[int] = Field(
        None,
        description="ID del documento en la base de datos local. "
                    "Solo se incluye cuando actions.persist=True.",
    )


# ---------------------------------------------------------------------------
# Schema de consulta de documento persistido
# ---------------------------------------------------------------------------

class DocumentRecord(BaseModel):
    """
    Representación del documento almacenado en BD.

    Retornado por GET /api/v1/documents/{document_id}.
    No incluye request_payload ni artefactos en base64 para mantener
    la respuesta liviana.
    """
    id: int
    company_id: int
    external_id: Optional[str] = None
    filename: str
    type: str
    document_type_id: str
    series: str
    number: str
    date_of_issue: Optional[str] = None
    state_type_id: str
    ticket: Optional[str] = None
    sunat_code: Optional[str] = None
    sunat_description: Optional[str] = None
    sunat_notes: List[str] = Field(default_factory=list)
    hash: Optional[str] = None
    xml_unsigned_path: Optional[str] = None
    xml_signed_path: Optional[str] = None
    cdr_path: Optional[str] = None
    attempt_count: int = 1
    processed_at: Optional[str] = None
    created_at: Optional[str] = None
    updated_at: Optional[str] = None


# ---------------------------------------------------------------------------
# Respuesta de consulta de ticket (flujo asíncrono: summary / voided)
# ---------------------------------------------------------------------------

class TicketStatusResponse(BaseModel):
    """
    Respuesta de la consulta de estado de un ticket SUNAT.

    SUNAT no procesa resúmenes/bajas de inmediato (suele tardar ~1 min).
    Si state_type_id == '03' (En proceso), volver a consultar más tarde.
    """
    ticket: str
    success: bool
    state_type_id: str = Field(
        description="03=En proceso, 05=Aceptado, 07=Observado con notas, 09=Rechazado"
    )
    state_description: str

    sunat_code: Optional[str] = None
    sunat_description: Optional[str] = None
    sunat_notes: List[str] = Field(default_factory=list)

    # CDR comprimido en base64 (disponible cuando state_type_id es 05 o 07)
    cdr_b64: Optional[str] = None

    errors: List[str] = Field(default_factory=list)
