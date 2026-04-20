# Objetivo

Crear un paquete Laravel para enviar documentos electrónicos a SUNAT, con soporte para múltiples modalidades (SUNAT directo, OSE, PSE), y un pipeline interno que maneje todo el proceso de envío, desde la construcción del XML hasta el manejo de la respuesta de SUNAT. El paquete debe ser desacoplado, extensible (para agregar nuevos proveedores), y fácil de usar, con una configuración centralizada y un sistema de logs robusto.


## 🗺️ Plan por fases
El paquete tiene como objetivo ser el canal desacoplado de envío a SUNAT, soportando 3 modalidades: SUNAT directo, OSE y PSE, con pipeline interno, base de datos propia (config + logs), y SunatAccount como modelo Eloquent.

### ✅ FASE 1 — Fundamentos: DocumentData, migraciones, SunatAccount
Lo que se entrega en esta fase:

1. DocumentData (DTOs/)
Fiel al nombre acordado — el DTO central del pipeline. Transporta toda la información necesaria para enviar un documento a SUNAT. Debe incluir:

ruc, serie, correlativo, tipo_documento
xml (contenido en string)
zip (opcional, generado por el pipeline)
fileName (nombre del archivo según nomenclatura SUNAT)
account (instancia de SunatAccount)
El archivo ya existe pero debe ser revisado para confirmar que todos estos campos están presentes y bien tipados con readonly / final.

2. Migraciones (database/migrations/)
Dos tablas que el paquete publica en el host app:

sunat_sender_config — configuración por tenant/cuenta:

Code
id, ruc, sol_user, sol_password (encrypted), certificate (encrypted),
business_name, trade_name, provider (sunat|ose|pse),
provider_url, provider_token, provider_key,
is_active, created_at, updated_at
sunat_sender_logs — registro de cada envío:

Code
id, ruc, document_type, serie, correlativo, file_name,
provider, request_at, response_at,
status (pending|sent|accepted|rejected|error),
sunat_code, sunat_description, ticket_number,
raw_request (nullable), raw_response (nullable),
created_at, updated_at
3. SunatAccount (Models/)
Modelo Eloquent que lee de sunat_sender_config. Reemplaza al DTO con el mismo nombre. El DTO DTOs/SunatAccount.php pasa a ser un Value Object de solo lectura que se construye desde el modelo Eloquent.

Flujo:

Code
SunatAccount (Model) → SunatAccount::toValueObject() → SunatAccountData (DTO readonly)
O bien, renombrar el DTO a SunatAccountData para no colisionar con el modelo.

### 🔜 FASE 2 — Providers: SUNAT, OSE, PSE
SunatProvider.php (envío SOAP directo)
OseProvider.php (envío a OSE vía REST/SOAP)
PseProvider.php (ya existe, completar)
AbstractSunatProvider.php (base común)
Contratos: ProviderInterface y AsyncProviderInterface (ya existen, revisar)

### 🔜 FASE 3 — Pipeline de envío
SendDocumentPipeline.php
Pipes: ValidateDocument, BuildZip, SignXml, SendToProvider, HandleResponse, WriteLog
SunatSenderService.php (orquestador, ya existe pero incompleto)

### 🔜 FASE 4 — Config, ServiceProvider, Facade
config/sunat-sender.php (driver por defecto, credenciales fallback)
SunatSenderServiceProvider.php (ya existe, completar con publish de migraciones y config)
Facades/SunatSender.php (ya existe)

### 🔜 FASE 5 — Tests y documentación
Tests de Unit para DTOs, Account, Response
Tests de Feature para cada provider (con mock HTTP)
README actualizado con uso real

## Estructura esperada del paquete

```
sunat-sender/
│
├── src/
│   ├── SunatSender.php                    ← Facade / entry point
│   ├── SunatSenderServiceProvider.php
│   │
│   ├── Contracts/
│   │   ├── ProviderInterface.php
│   │   └── AsyncProviderInterface.php
│   │
│   ├── DTOs/
│   │   ├── SunatAccount.php               ← readonly class
│   │   └── SunatResponse.php              ← readonly class
│   │
│   ├── Models/
│   │   ├── Provider.php
│   │   ├── SunatAccount.php
│   │   └── Document.php
│   │
│   ├── Providers/
│   │   ├── BaseSunatProvider.php
│   │   ├── SunatDirectProvider.php
│   │   ├── OseProvider.php
│   │   └── PseProvider.php
│   │
│   ├── Support/
│   │   ├── ProviderFactory.php
│   │   ├── ProviderRegistry.php
│   │   └── AccountRepository.php
│   │
│   ├── Pipeline/
│   │   ├── DocumentPipeline.php
│   │   ├── Steps/
│   │   │   ├── NormalizeDocument.php
│   │   │   ├── GenerateXml.php
│   │   │   ├── SignXml.php
│   │   │   ├── SendToProvider.php
│   │   │   └── RecordDocument.php
│   │
│   ├── Normalizer/
│   │   ├── DocumentNormalizer.php
│   │   └── TaxCalculator.php
│   │
│   ├── Xml/
│   │   ├── XmlGenerator.php
│   │   └── XmlSigner.php
│   │
│   ├── WS/
│   │   ├── Client/
│   │   │   ├── WsClient.php
│   │   │   └── WsSecurityHeader.php
│   │   ├── Services/
│   │   │   ├── BaseSunat.php
│   │   │   ├── BillSender.php
│   │   │   ├── SummarySender.php
│   │   │   ├── ExtService.php
│   │   │   └── ConsultCdrService.php
│   │   ├── Response/
│   │   │   ├── BaseResult.php
│   │   │   ├── BillResult.php
│   │   │   ├── SummaryResult.php
│   │   │   ├── StatusResult.php
│   │   │   ├── StatusCdrResult.php
│   │   │   ├── CdrResponse.php
│   │   │   └── SunatError.php
│   │   ├── Reader/
│   │   │   └── DomCdrReader.php
│   │   ├── Validator/
│   │   │   └── XmlErrorCodeProvider.php
│   │   └── Zip/
│   │       ├── ZipFly.php
│   │       └── ZipFileDecompress.php
│   │
│   ├── Catalog/
│   │   ├── DocumentTypes.php              ← const string PHP 8.3
│   │   ├── AffectationIgvTypes.php        ← enum
│   │   ├── UnitTypes.php                  ← enum
│   │   ├── CurrencyTypes.php              ← enum
│   │   ├── IdentityDocumentTypes.php      ← enum
│   │   └── SunatEndpoints.php
│   │
│   └── Exceptions/
│       ├── SunatSenderException.php
│       ├── AccountNotFoundException.php
│       ├── ValidationException.php
│       └── ProviderException.php
│
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_sunat_sender_providers_table.php
│       ├── 2024_01_01_000002_create_sunat_sender_accounts_table.php
│       └── 2024_01_01_000003_create_sunat_sender_documents_table.php
│
├── config/
│   └── sunat-sender.php
│
├── tests/
│   ├── TestCase.php
│   ├── Unit/
│   │   ├── Normalizer/
│   │   └── Catalog/
│   └── Feature/
│       └── Pipeline/
│
├── composer.json
├── phpunit.xml
├── .gitignore
├── CHANGELOG.md
└── README.md
```
### Estructura de DocumentData

El contrato del array de entrada — los 9 tipos de documento
Todo tipo de documento comparte una estructura base + sección específica. Acá el schema completo, diferenciando lo obligatorio de lo opcional:

Base común (todos los tipos)
```PHP
[
    // --- Identificación del emisor (el paquete lo completa desde la cuenta) ---
    'account_id'     => 1,          // O bien 'ruc' => '20123456789'
    // (si se pasa account_id/ruc, el paquete resuelve la cuenta;
    //  si se llama Facturalo::forAccount()->process(), se omite aquí)

    // --- Cabecera del documento ---
    'document_type'  => '01',       // Catálogo 01 SUNAT: 01=Factura, 03=Boleta, 07=NC, 08=ND
    'series'         => 'F001',
    'number'         => 1,
    'date_of_issue'  => '2026-03-30',
    'time_of_issue'  => '10:30:00', // opcional, default 00:00:00
    'currency_type'  => 'PEN',      // Catálogo 02

    // --- Establecimiento (del emisor) ---
    'establishment'  => [
        'address'    => 'Av. Principal 123',
        'district'   => '150101',   // ubigeo
        'province'   => '1501',
        'department' => '15',
        'country'    => 'PE',
    ],

    // --- Cliente/Destinatario ---
    'customer' => [
        'identity_document_type' => '6',    // Catálogo 06: 6=RUC, 1=DNI
        'number'                 => '20123456789',
        'name'                   => 'Cliente SAC',
        'address'                => 'Av. Lima 456',  // opcional
        'email'                  => 'cliente@mail.com', // opcional
    ],
]
```
Por tipo de documento

Factura / Boleta (01, 03) — el más completo
```PHP
[
    ...base,

    // Referencias (opcionales)
    'purchase_order'      => 'OC-001',  // opcional
    'detraction'          => [...],     // opcional
    'perception'          => [...],     // opcional
    'prepayments'         => [...],     // opcional

    // Descuentos globales (opcional)
    'global_discounts' => [
        ['factor' => 0.05, 'base' => 100.00, 'amount' => 5.00],
    ],

    // Ítems (obligatorio, al menos 1)
    'items' => [
        [
            'internal_id'        => 'P001',       // código interno del host
            'description'        => 'Producto X',
            'unit_type'          => 'NIU',         // Catálogo 03
            'quantity'           => 2.00,
            'unit_value'         => 50.00,         // precio sin IGV
            'unit_price'         => 59.00,         // precio con IGV

            'affectation_igv_type' => '10',        // Catálogo 07: 10=Gravado
            'igv_percentage'       => 18,          // default 18

            // Opcionales por ítem
            'isc_type'           => null,          // Catálogo 08
            'isc_percentage'     => 0,
            'has_icbper'         => false,
            'icbper_amount'      => 0,

            'discounts' => [                       // descuentos por ítem (opcional)
                ['factor' => 0.10, 'base' => 100.00, 'amount' => 10.00],
            ],
            'attributes' => [...],                 // atributos adicionales UBL (opcional)
        ],
    ],

    // Totales (el paquete puede calcularlos o recibirlos si el host los calcula)
    // Si no se pasan, el paquete los calcula desde los ítems
    'totals' => [
        'total_taxed'            => 100.00,
        'total_exonerated'       => 0,
        'total_free'             => 0,
        'total_unaffected'       => 0,
        'total_igv'              => 18.00,
        'total_isc'              => 0,
        'total_icbper'           => 0,
        'total_discounts'        => 0,
        'total_charge'           => 0,
        'total_exportation'      => 0,
        'total_value'            => 100.00,
        'total'                  => 118.00,
    ],

    // Pagos (obligatorio en boleta, opcional en factura)
    'payment_method_type' => 'Contado',  // 'Contado' | 'Credito'
    'fees' => [                          // cuotas, solo si Credito
        ['date' => '2026-04-30', 'amount' => 59.00],
    ],
]
```
Nota de Crédito / Débito (07, 08)
```PHP
[
    ...base,
    'note_type'          => '01',    // Catálogo 09 (NC) o 10 (ND)
    'note_description'   => 'Anulación de la operación',
    'affected_document'  => [
        'document_type'  => '01',
        'series'         => 'F001',
        'number'         => 1,
    ],
    'items' => [...],   // misma estructura que factura
    'totals' => [...],
]
```
Resumen Diario (RC) — boletas del día
```PHP
[
    'document_type'  => 'RC',
    'series'         => 'RC-20260330',
    'date_of_issue'  => '2026-03-30',
    'date_reference' => '2026-03-30',   // fecha de las boletas resumidas
    'account_id'     => 1,
    'tickets'        => [               // array de boletas
        [
            'document_type'   => '03',
            'series'          => 'B001',
            'number'          => 1,
            'state'           => '1',  // 1=emitido, 2=anulado, 3=reversal
            'total_taxed'     => 100.00,
            'total_igv'       => 18.00,
            'total'           => 118.00,
            'customer_identity_type' => '1',
            'customer_number'        => '12345678',
        ],
    ],
]
```
Comunicación de Baja (RA)
```PHP
[
    'document_type'  => 'RA',
    'series'         => 'RA-20260330',
    'date_of_issue'  => '2026-03-30',
    'date_reference' => '2026-03-30',
    'account_id'     => 1,
    'tickets'        => [
        [
            'document_type'   => '01',
            'series'          => 'F001',
            'number'          => 5,
            'description'     => 'ERROR EN RUC DEL CLIENTE',
        ],
    ],
]
```
Retención (20)
```PHP
[
    ...base,
    'document_type'          => '20',
    'regime_type'            => '01',      // 01=3%, 02=6%
    'observation'            => null,
    'total_amount'           => 118.00,
    'total_retention_amount' => 3.54,
    'documents' => [
        [
            'document_type'       => '01',
            'series'              => 'F001',
            'number'              => 10,
            'date_of_issue'       => '2026-03-01',
            'payment_date'        => '2026-03-30',
            'total_amount'        => 118.00,
            'net_amount'          => 114.46,
            'retention_amount'    => 3.54,
            'currency_type'       => 'PEN',
            'exchange_rate'       => 1,
        ],
    ],
]
```
Percepción (40)
```PHP
[
    ...base,
    'document_type'           => '40',
    'regime_type'             => '01',     // 01=Venta interna 2%
    'observation'             => null,
    'total_amount'            => 118.00,
    'total_perception_amount' => 2.36,
    'documents' => [
        [
            'document_type'      => '01',
            'series'             => 'F001',
            'number'             => 11,
            'date_of_issue'      => '2026-03-01',
            'payment_date'       => '2026-03-30',
            'total_amount'       => 118.00,
            'net_amount'         => 120.36,
            'perception_amount'  => 2.36,
            'currency_type'      => 'PEN',
            'exchange_rate'      => 1,
        ],
    ],
]
```
Guía de Remisión (09)
```PHP
[
    ...base,
    'document_type'      => '09',
    'transfer_reason'    => '01',          // Catálogo 20: 01=Venta, 04=Traslado entre establecimientos
    'transport_mode'     => '01',          // 01=Transporte público, 02=Privado
    'transfer_date'      => '2026-03-30',
    'observation'        => null,
    'gross_weight'       => 10.500,
    'weight_unit'        => 'KGM',
    'packages_number'    => 1,
    'carrier' => [                         // solo si transporte público
        'identity_document_type' => '6',
        'number'                 => '20111111111',
        'name'                   => 'Transportes SAC',
        'license_plate'          => 'ABC-123',
    ],
    'driver' => [...],                     // opcional
    'origin' => [
        'address'   => 'Av. Principal 123',
        'ubigeo'    => '150101',
    ],
    'destination' => [
        'address'   => 'Av. Destino 456',
        'ubigeo'    => '150201',
    ],
    'related_documents' => [               // documentos relacionados (opcional)
        ['document_type' => '01', 'series' => 'F001', 'number' => 1],
    ],
    'items' => [
        [
            'description'    => 'Producto X',
            'unit_type'      => 'NIU',
            'quantity'       => 2.00,
            'internal_id'    => 'P001',    // opcional
        ],
    ],
]
```
Liquidación de Compra (03 especial con flag)
```PHP
[
    ...base,
    'document_type'   => 'LC',
    'supplier' => [   // proveedor (persona natural sin RUC)
        'identity_document_type' => '4',  // 4=Carnet extranjería, 7=pasaporte
        'number'                 => '12345678',
        'name'                   => 'Juan Quispe',
        'address'                => 'Comunidad Los Andes',
    ],
    'items'  => [...],
    'totals' => [...],
]
```

### Flujo esperado de uso del paquete
El flujo completo con la BD propia
```
Host                              Paquete
─────                             ─────────────────────────────────
1. Registra la cuenta una vez:
   Facturalo::registerAccount([
     'ruc'         => '20123...',
     'sol_user'    => 'MODDATOS',
     'sol_password'=> 'MODDATOS',
     'certificate' => file_get_contents(...),
     'environment' => 'beta',
     'provider'    => 'sunat',     ← slug en facturalo_providers
   ]);                             → guarda en facturalo_accounts

2. Cada vez que emite:
   $response = Facturalo
       ::forAccount('20123456789') ← busca en facturalo_accounts
       ->process($array);          ← pipeline completo

   Internamente el paquete:
   ├─ AccountRepository::findByRuc() → FacturaloAccount
   ├─ ProviderFactory::make($account) → ProviderInterface
   ├─ Normalizer::normalize($array) → DocumentData DTO
   ├─ XmlGenerator::build($data) → $xmlSigned (string)
   ├─ $provider->sendBill($filename, $xmlSigned) → BillResult
   ├─ DocumentRepository::record($data, $result) → guarda en facturalo_documents
   └─ FacturaloResponse::fromResult($result, $data)

3. Host recibe FacturaloResponse y
   actualiza sus propios modelos.
```

### Proque 3 tablas

Resumen de las 3 tablas y por qué son suficientes

Tabla	Propósito	Quién la gestiona
facturalo_providers	Catálogo de drivers: SUNAT, OSE-X, PSE-Y, con sus WSDL URLs. Viene pre-poblada con SUNAT, el host puede agregar más	Migración del paquete + ProviderRegistry
facturalo_accounts	Un registro por empresa emisora. RUC + credenciales encriptadas + cert + entorno + provider	Facturalo::registerAccount() / AccountManager
facturalo_documents	El trazado mínimo de cada documento enviado. Hash, estado, ticket, paths. Sin datos de negocio	Pipeline del paquete automáticamente


## Producto original

El proyecto original es Laravel 9, pero el paquete se desarrollará en Laravel 10+ para aprovechar las últimas características de PHP 8.3 (readonly classes, const string, etc.) y asegurar compatibilidad futura. El host app puede seguir en Laravel 9 sin problemas, ya que el paquete se diseñará para ser compatible con Laravel 9+.

### Ubicaciones del proyecto original en el cual se basa el desarrollo del paquete
1. repo privado https://github.com/rashArt/fork-fperu-pro7/tree/main/app/CoreFacturalo
2. repo publico https://github.com/rashArt/facturaloperu-pro-5/tree/master/app/CoreFacturalo
3. local E:\www\facturaloperu-pro8\app\CoreFacturalo