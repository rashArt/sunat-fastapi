@php
    $establishment = $document->establishment;
    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
@endphp
<html>
<head>
</head>
<body>
@php
    $logo = null;

    if (!empty($establishment->logo)) {
        $logo = $establishment->logo;
    } elseif (!empty($company->logo)) {
        $logo = "storage/uploads/logos/{$company->logo}";
    }
@endphp

@if($logo)
    <div class="item_watermark" style="
        position: absolute;
        top: 35%;
        left: 10%;
        width: 80%;
        height: 300px;
        text-align: center;
    ">
        <img
            src="data:{{ mime_content_type(public_path($logo)) }};base64,{{ base64_encode(file_get_contents(public_path($logo))) }}"
            alt="{{ $company->name }}"
            style="width: 100%; height: auto; object-fit: contain; opacity: 0.1;"
        >
    </div>
@endif
<table class="full-width">
    <tr>
        @if($company->logo)
            <td width="10%">
                <img src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}" alt="{{ $company->name }}" class="company_logo" style="max-width: 300px">
            </td>
                <td width="50%" class="text-center">
                <div class="text-left">
                    <h3 class="">{{ $company->name }}</h3>
                    <h4>{{ 'RUC '.$company->number }}</h4>
                    <h5 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h5>
                    <h5>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h5>
                    <h5>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h5>
                </div>
            </td>
            <td width="40%" class="border-box p-4 text-center">
                <h4 class="text-center">{{ $document->document_type->description }}</h4>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @else
            <td width="50%" class="pl-1">
                <div class="text-left">
                    <h3 class="">{{ $company->name }}</h3>
                    <h4>{{ 'RUC '.$company->number }}</h4>
                    <h5 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h5>
                    <h5>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h5>
                    <h5>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h5>
                </div>
            </td>
            <td width="40%" class="border-box p-4 text-center">
                <h4 class="text-center">{{ $document->document_type->description }}</h4>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @endif        
    </tr>
</table>
<table class="full-width border-box mt-10 mb-10">
    <tbody>
    <tr>
        <td>Fecha Emisión: {{ $document->date_of_issue->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td>Fecha Inicio de Traslado: {{ $document->date_of_shipping->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td>Peso Bruto Total({{ $document->unit_type_id }}): {{ $document->total_weight }}</td>
    </tr>
    <tr>
        <td>
            Punto de Partida: {{ $document->sender_address_data['location_id'] }}
            - {{ $document->sender_address_data['address'] }},
            @php
                $district = App\Models\Tenant\Catalogs\District::find($document->sender_address_data['location_id']);
            @endphp
            {{ $district->description }}, {{ $district->province->description }}, {{ $district->province->department->description }}
        </td>
    </tr>
    <tr>
        <td>
            Punto de Llegada: {{ $document->receiver_address_data['location_id'] }}
            - {{ $document->receiver_address_data['address'] }},
            @php
                $district = App\Models\Tenant\Catalogs\District::find($document->receiver_address_data['location_id']);
            @endphp
            {{ $district->description }}, {{ $district->province->description }}, {{ $district->province->department->description }}
        </td>
    </tr>
    <tr>
        <td>Datos del Remitente: {{ $document->sender_data['name'] }}
            - {{ $document->sender_data['identity_document_type_description'] }} {{ $document->sender_data['number'] }}</td>
    </tr>
    <tr>
        <td>Datos del Destinatario: {{ $document->receiver_data['name'] }}
            - {{ $document->receiver_data['identity_document_type_description'] }} {{ $document->receiver_data['number'] }}</td>
    </tr>
    </tbody>
</table>

@if($document['reference_documents'])
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="2">DOCUMENTOS RELACIONADOS</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document['reference_documents'] as $row)
            <tr>
                <td>{{ $row['document_type']['description'] }}: {{ $row['number'] }}</td>
                <td>RUC: {{ $row['customer'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<table class="full-width border-box mt-10 mb-10">
    <thead>
    <tr>
        <th class="border-bottom text-left" colspan="2">TRANSPORTE</th>
    </tr>
    </thead>
    <tbody>
    @if($document->is_transport_m1l)
    <tr>
        @if($document->is_transport_m1l)
            <td>Indicador de traslado en vehículos de categoría M1 o L: SI</td>
        @endif
        @if($document->license_plate_m1l)
            <td>Placa de vehículo: {{ $document->license_plate_m1l}}</td>
        @endif
    </tr>
    @endif
    @if($document->transport_mode_type_id === '01' && !$document->is_transport_m1l)
        @php
            $document_type_dispatcher = App\Models\Tenant\Catalogs\IdentityDocumentType::findOrFail($document->dispatcher->identity_document_type_id);
        @endphp
        <tr>
            <td>Nombre y/o razón social: {{ $document->dispatcher->name }}</td>
            <td>{{ $document_type_dispatcher->description }}: {{ $document->dispatcher->number }}</td>
        </tr>
    @else
        @if(!$document->is_transport_m1l)
        <tr>
            @if($document->transport_data)
                <td>Número de placa del vehículo Principal: {{ $document->transport_data['plate_number'] }}</td>
            @endif
            @if(isset($document->transport_data['tuc']) && $document->transport_data['tuc'])
                <td>Certificado de habilitación vehicular: {{ $document->transport_data['tuc'] }}</td>
            @endif
        </tr>
        <tr>
            @if($document->driver->number)
                <td>Conductor Principal: {{$document->driver->name}}</td>
            @endif
            @if($document->driver->number)
                <td>Documento de conductor: {{ $document->driver->number }}</td>
            @endif
        </tr>
        <tr>
            @if($document->secondary_license_plates)
                @if($document->secondary_license_plates->semitrailer)
                    <td>Número de placa semirremolque: {{ $document->secondary_license_plates->semitrailer }}</td>
                @endif
            @endif
            @if($document->driver->license)
                <td>Licencia del conductor: {{ $document->driver->license }}</td>
            @endif
        </tr>
        @endif
    @endif
    </tbody>
</table>

@if($document->secondary_transports && !$document->is_transport_m1l)
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="2">Vehículos Secundarios</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->secondary_transports as $row)
        <tr>
            @if($row["plate_number"])
                <td>Número de placa del vehículo: {{ $row["plate_number"] }}</td>
            @endif
            @if($row['tuc'])
                <td>Certificado de habilitación vehicular: {{ $row['tuc'] }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
@endif
@if($document->secondary_drivers && !$document->is_transport_m1l)
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="3">Conductores Secundarios</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->secondary_drivers as $row)
        <tr>
            @if($row['name'])
                <td>Conductor: {{$row['name']}}</td>
            @endif
            @if($row['number'])
                <td>Documento: {{ $row['number'] }}</td>
            @endif
            @if($row['license'])
                <td>Licencia: {{ $row['license'] }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
@endif

@php
$showSerie = false;
$showModel = false;
$showBrand = false;
$showLot = false;
$showDateDue = false;

$itemLotGroup = app('App\Services\ItemLotsGroupService');

foreach($document->items as $row) {
    if (!empty($row->item->lots)) {
        foreach ($row->item->lots as $lot) {
            if (!empty($lot->series)) {
                $showSerie = true;
                break;
            }
        }
    }

    if (!empty($row->item->model)) {
        $showModel = true;
    }

    if (!empty($row->relation_item->brand->name ?? null)) {
        $showBrand = true;
    }

    $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
    $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);

    if (!empty($lot)) {
        $showLot = true;
    }

    if (!empty($date_due) || !empty($row->relation_item->date_of_due)) {
        $showDateDue = true;
    }

    if ($showSerie && $showModel && $showBrand && $showLot && $showDateDue) {
        break;
    }
}
@endphp

<table class="full-width border-box mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center">Item</th>
        <th class="border-top-bottom text-center">Código</th>
        <th class="border-top-bottom text-left">Descripción</th>
        @if($showSerie)
            <th class="border-top-bottom text-left">Serie</th>
        @endif
        @if($showModel)
            <th class="border-top-bottom text-left">Modelo</th>
        @endif
        @if($showBrand)
            <th class="border-top-bottom text-center">Marca</th>
        @endif
        @if($showLot)
            <th class="border-top-bottom text-center">Lote</th>
        @endif
        @if($showDateDue)
            <th class="border-top-bottom text-center">F. Venc.</th>
        @endif
        <th class="border-top-bottom text-center">Unidad</th>
        <th class="border-top-bottom text-right">Cantidad</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        <tr>
            <td class="text-center">{{ $loop->iteration }}</td>
            <td class="text-center">{{ $row->item->internal_id }}</td>
            <td class="text-left">
                @if($row->name_product_pdf)
                    {!!$row->name_product_pdf!!}
                @else
                    {!!$row->item->description!!}
                @endif

                @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif

                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/><span style="font-size: 9px">{!! $attr->description !!} : {{ $attr->value }}</span>
                    @endforeach
                @endif
                @if($row->discounts)
                    @foreach($row->discounts as $dtos)
                        <br/><span style="font-size: 9px">{{ $dtos->factor * 100 }}% {{$dtos->description }}</span>
                    @endforeach
                @endif
                @if($row->relation_item->is_set == 1)
                    <br>
                    @inject('itemSet', 'App\Services\ItemSetService')
                    @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                        {{$item}}<br>
                    @endforeach
                @endif

                @if($document->has_prepayment)
                    <br>
                    *** Pago Anticipado ***
                @endif
            </td>
            @if($showSerie)
            <td class="text-left align-top">
                @isset($row->item->lots)
                    @foreach($row->item->lots as $lot)
                        @if( isset($lot->has_sale) && $lot->has_sale)
                            <span style="font-size: 9px">{{ $lot->series }}</span><br>
                        @endif
                    @endforeach
                @endisset
            </td>
            @endif
            @if($showModel)
            <td class="text-left">{{ $row->item->model ?? '' }}</td>
            @endif
            @if($showBrand)
            <td class="text-center align-top">{{ $row->relation_item->brand->name ?? '' }}</td>
            @endif
            @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
            @php
                $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
                $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);
            @endphp

            @if($showLot)
                <td class="text-center align-top">
                    @if($lot)
                        @foreach(explode('/', $lot) as $code)
                            @if(trim($code) !== '')
                                {{ trim($code) }}<br>
                            @endif
                        @endforeach
                    @endif
                </td>
            @endif
            @if($showDateDue)
                <td class="text-center align-top">
                    @php
                        $cleanedDate = $date_due != ''
                            ? ltrim($date_due, '/')
                            : ($row->relation_item->date_of_due ? $row->relation_item->date_of_due->format('Y-m-d') : '');
                    @endphp
            
                    {{ $cleanedDate }}
                </td>
            @endif
            <td class="text-center">{{ $row->item->unit_type_id }}</td>
            <td class="text-right">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@if($document->observations)
    <table class="full-width border-box mt-10 mb-10">
        <tr>
            <td class="text-bold border-bottom font-bold">OBSERVACIONES</td>
        </tr>
        <tr>
            <td>{{ $document->observations }}</td>
        </tr>
    </table>
@endif
@if($document->qr)
    <table class="full-width">
        <tr>
            <td class="text-left">
                <img src="data:image/png;base64, {{ $document->qr }}" style="margin-right: -10px;"/>
            </td>
        </tr>
    </table>
@endif
</body>
</html>
