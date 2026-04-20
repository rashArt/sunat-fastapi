@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');

    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    // $document_type_driver = App\Models\Tenant\Catalogs\IdentityDocumentType::findOrFail($document->driver->identity_document_type_id);

@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>
<table class="full-width">
    <tr>
        @if($company->logo)
            <td width="20%">
                <img
                    src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}"
                    alt="{{$company->name}}" alt="{{ $company->name }}" class="company_logo" style="max-width: 300px">
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
            <td width="30%" class="border-box p-4 text-center">
                <h4 class="text-center">{{ $document->document_type->description }}</h4>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @else
            <td width="70%" class="pl-1 text-left">
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
            <td width="30%" class="border-box p-4 text-center">
                <h4 class="text-center">{{ $document->document_type->description }}</h4>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @endif

    </tr>
</table>
@if($document->transfer_reason_type_id === '04')
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">{{ $document['transfer_reason_type_id'] != '02' ? 'DESTINATARIO' : 'PROVEEDOR' }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Razón Social: {{ $company->name }}</td>
        </tr>
        <tr>
            <td>RUC: {{ $company->number }}
            </td>
        </tr>
        </tbody>
    </table>
@else
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">{{ $document['transfer_reason_type_id'] != '02' ? 'DESTINATARIO' : 'PROVEEDOR' }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Razón Social: {{ $customer->name }}</td>
        </tr>
        <tr>
            <td>{{ $customer->identity_document_type->description }}: {{ $customer->number }}
            </td>
        </tr>
        <tr>
            @php
                $ubigeo = App\Models\Tenant\Catalogs\District::find($customer->district_id);
            @endphp
            @if($document->transfer_reason_type_id === '09')
                <td>Dirección: {{ $customer->address }} - {{ $customer->country->description }}
                </td>
            @else
                <td>Dirección: {{ $customer->address }}
                    @if ($ubigeo)
                        {{ ($customer->district_id !== '-')? ', '.$ubigeo->description : '' }}
                        {{ ($customer->province_id !== '-')? ', '.$ubigeo->province->description : '' }}
                        {{ ($customer->department_id !== '-')? '- '.$ubigeo->province->department->description : '' }}
                    @endif
                </td>
            @endif
        </tr>
        @if ($customer->telephone)
            <tr>
                <td>Teléfono:{{ $customer->telephone }}</td>
            </tr>
        @endif
        <tr>
            <td>Vendedor: {{ $document->user->name }}</td>
        </tr>
        </tbody>
    </table>
@endif

@if ($document['transfer_reason_type_id'] == '02')
    <table class="full-width border-box mt-10 mb-10">
    @php
        // dd($document->toArray());
        $company = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getInformationCompany();
        // dd($company);
    @endphp
        <thead>
        <tr>
            <th class="border-bottom text-left">DESTINATARIO</th>
        </tr>
        </thead>
    <tbody>
    <tr>
        <td>Razón Social: {{ $company['company']->name }}</td>
    </tr>
    <tr>
        <td>RUC: {{ $company['company']->number }}
        </td>
    </tr>
    <tr>
        <td>Dirección: {{ $company['establishment']->address }}
            {{-- {{ ($customer->district_id !== '-')? ', '.$customer->district->description : '' }}
            {{ ($customer->province_id !== '-')? ', '.$customer->province->description : '' }}
            {{ ($customer->department_id !== '-')? '- '.$customer->department->description : '' }} --}}
        </td>
    </tr>
    @if ($company['establishment']->telephone)
    <tr>
        <td>Teléfono:{{ $company['establishment']->telephone }}</td>
    </tr>
    @endif
    </tbody>

    </table>
@endif

@if ($document['transfer_reason_type_id'] == '03')
    @php
        $buyer = $document->buyer;
        $identify_description = App\Models\Tenant\Catalogs\IdentityDocumentType::find($buyer->identity_document_type_id)->description;
    @endphp
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">COMPRADOR</th>
        </tr>
        </thead>
    <tbody>
    <tr>
        <td>Razón Social: {{ $buyer->name }}</td>
    </tr>
    <tr>
        <td>{{ $identify_description }}: {{ $buyer->number }}
        </td>
    </tr>
    <tr>
        <td>Dirección: {{ $buyer->address }}
        </td>
    </tr>
    </tbody>

    </table>

@endif

@if($document['reference_documents'])
    {{-- @if ($document->transfer_reason_type->description == "Compra")
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="2">PROVEEDOR</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document['reference_documents'] as $row)
            <tr>
                <td>RAZON SOCIAL: {{ $row['name'] }}</td>
                <td>RUC: {{ $row['customer'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif --}}
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
            </tr>
            <tr>
                <td>PROOVEDOR {{ $row['name'] }}</td>
                <td>RUC: {{ $row['customer'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
<table class="full-width border-box mt-10 mb-10">
    <thead>
    <tr>
        <th class="border-bottom text-left" colspan="2">ENVIO</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Fecha Emisión: {{ $document->date_of_issue->format('Y-m-d') }}</td>
        <td>Fecha Inicio de Traslado: {{ $document->date_of_shipping->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td>Motivo Traslado: {{ $document->transfer_reason_type->description }}</td>
        <td>Modalidad de Transporte: {{ $document->transport_mode_type->description }}</td>
    </tr>

    @if($document->transfer_reason_description)
        <tr>
            <td colspan="2">Descripción de motivo de traslado: {{ $document->transfer_reason_description }}</td>
        </tr>
    @endif

    @if($document->related)
        <tr>
            <td>Número de documento (DAM): {{ $document->related->number }}</td>
            <td>Tipo documento relacionado: {{ $document->getRelatedDocumentTypeDescription() }}</td>
        </tr>
    @endif

    <tr>
        <td>Peso Bruto Total({{ $document->unit_type_id }}): {{ $document->total_weight }}</td>
        @if($document->packages_number)
            <td>Número de Bultos: {{ $document->packages_number }}</td>
        @endif
    </tr>
    <tr>
        <td colspan="2">
            {{-- P.Partida: {{ $document->origin->location_id }} - {{ $document->origin->address }} --}}
            @php
                $direction_label_origin = $document['transfer_reason_type_id'] != '02' ? 'P.Partida:': 'P.Llegada:';
                $origin = $document->origin;
                $ubigeo_origin = App\Models\Tenant\Catalogs\District::find($origin->location_id);
            @endphp
            {{ $direction_label_origin }}
            {{ ($origin->address !== '-')? $origin->address : '' }}
            {{ (isset($ubigeo_origin)) ? ', '.$ubigeo_origin->description : '' }}
            {{ (isset($ubigeo_origin))? ', '.$ubigeo_origin->province->description : '' }}
            {{ (isset($ubigeo_origin))? '- '.$ubigeo_origin->province->department->description : '' }}
        </td>
    </tr>
    <tr>
        <td colspan="2">
            {{-- P.Llegada: {{ $document->delivery->location_id }} - {{ $document->delivery->address }} --}}
            @php
                $delivery = Illuminate\Support\Facades\DB::connection('tenant')->table('districts')
                    ->join('provinces', 'districts.province_id', '=', 'provinces.id')
                    ->join('departments', 'provinces.department_id', '=', 'departments.id')
                    ->where('districts.id', '=', $document->delivery->location_id)
                    ->select('districts.description as district_description', 'provinces.description as province_description','departments.description as department_description')
                    ->first();
                $direction_label_delivery = $document['transfer_reason_type_id'] == '02' ? 'P.Partida:': 'P.Llegada:';
            @endphp
            {{ $direction_label_delivery }}
            {{  $document->delivery->address  }}
            {{ ($delivery->district_description !== '-')? ', '.$delivery->district_description : '' }}
            {{ ($delivery->province_description !== '-')? ', '.$delivery->province_description : '' }}
            {{ ($delivery->department_description !== '-')? '- '.$delivery->department_description : '' }}
        </td>
    </tr>
    <tr>
        @if($document->order_form_external)
            <td>Orden de pedido: {{ $document->order_form_external }}</td>
        @endif
        @if($document->date_delivery_to_transport)
            <td>Fecha de entrega de bienes al Transportista: {{$document->date_delivery_to_transport->format('Y-m-d')}}</td>
        @endif
    </tr>
    </tbody>
</table>
@if ($document->custom_fields_data && is_array($document->custom_fields_data) && count($document->custom_fields_data) > 0)
<table class="full-width border-box mt-10 mb-10">
    {{-- <tr>
        <td class="text-bold border-bottom font-bold">CAMPOS PERSONALIZADOS</td>
    </tr> --}}
    @foreach($document->custom_fields_data as $field_slug => $field_value)
        @php
            $custom_field = \Modules\CustomField\Models\CustomField::where('slug', $field_slug)->first();
            $field_name = $custom_field ? $custom_field->name : $field_slug;
        @endphp
        <tr>
            <td>
                <strong>{{ $field_name }}:</strong>
                @if (is_array($field_value))
                    {{ implode(', ', $field_value) }}
                @else
                    {{ $field_value }}
                @endif
            </td>
        </tr>
    @endforeach
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
        @if ($document->has_transport_driver_01)
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
    @if( isset($configuration) && $configuration["enabled_price_items_dispatch"])
    <tr>
        <th class="border-top-bottom text-center" width="8%">Item</th>
        <th class="border-top-bottom text-center" width="8%">Código</th>
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
        <th class="border-top-bottom text-center" width="8%">Unidad</th>
        @if (isset($configuration['enable_weight_in_dispatches']) &&  $configuration['enable_weight_in_dispatches'])
            <th class="border-top-bottom text-center">Peso</th>
        @endif
        <th class="border-top-bottom text-center" width="9%">Cantidad</th>
        <th class="border-top-bottom text-center"width="8%">Precio</th>
        <th class="border-top-bottom text-right"width="8%">Total</th>
    </tr>
    @else
    <tr>
        <th class="border-top-bottom text-center">Item</th>
        <th class="border-top-bottom text-center">Código</th>
        <th class="border-top-bottom text-left">Descripción</th>
        <th class="border-top-bottom text-left">Serie</th>
        <th class="border-top-bottom text-left">Modelo</th>
        <th class="border-top-bottom text-center">Unidad</th>
        @if (isset($configuration['enable_weight_in_dispatches']) &&  $configuration['enable_weight_in_dispatches'])
            <th class="border-top-bottom text-center">Peso</th>
        @endif
        <th class="border-top-bottom text-right">Cantidad</th>
    </tr>
    @endif
    </thead>
    <tbody>
    @foreach($document->items as $row)
        @if(isset($configuration) && $configuration["enabled_price_items_dispatch"])
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
            @if (isset($configuration['enable_weight_in_dispatches']) &&  $configuration['enable_weight_in_dispatches'])
                <td class="text-center">{{ $row->item->weight }}</td>
            @endif
            <td class="text-center">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 2) }}
                @endif
            </td>
            <td class="text-center">{{ number_format($row->item->unit_price, 2) }}</td>
            <td class="text-right">{{ number_format($row->item->total, 2) }}</td>
        </tr>
        @else
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
            {{-- <td class="text-left">
                @php
                    $current_item = $items ? $items->where('item_id', $row->item_id)->first() : null;
                @endphp
                @if($current_item && count($current_item->item->lots) > 0)
                    @foreach($current_item->item->lots as $lot)
                        {{$lot->series}}
                        @if(!$loop->first && $loop->last)
                            -
                        @endif
                    @endforeach
                @endif
            </td> --}}
            <td class="text-left">{{ $row->item->model ?? '' }}</td>
            {{-- <td class="text-left">
                @php
                    $current_item = $items ? $items->where('item_id', $row->item_id)->first() : null;
                @endphp
                @if($current_item && count($current_item->item->lots) > 0)
                    @foreach($current_item->item->lots as $lot)
                        {{$lot->series}}
                        @if(!$loop->first && $loop->last)
                            -
                        @endif
                    @endforeach
                @endif
            </td> --}}
            <td class="text-left">{{ $row->item->model ?? '' }}</td>
            <td class="text-center">{{ $row->item->unit_type_id }}</td>
            @if (isset($configuration['enable_weight_in_dispatches']) &&  $configuration['enable_weight_in_dispatches'])
                <td class="text-center">{{ $row->item->weight }}</td>
            @endif
            <td class="text-right">
                @if ($row->item->unit_type_id == 'NIU')
                    {{ number_format($row->quantity, 0) }}
                @else 
                    {{ $row->quantity }}
                @endif
            </td>
         </tr>
        @endif
    @endforeach
    </tbody>
</table>

{{-- @php

$invoice = $document->reference_document()->where('id', $document->reference_document_id)->first();
$items = $invoice ? $invoice->items : null;
@endphp --}}

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

@if ($document->reference_document)
    <table class="full-width border-box">
        @if($document->reference_document)
            <tr>
                <td class="text-bold border-bottom font-bold">{{$document->reference_document->document_type->description}}</td>
            </tr>
            <tr>
                <td>{{ ($document->reference_document) ? $document->reference_document->number_full : "" }}</td>
            </tr>
        @endif
    </table>
@endif
@if ($document->data_affected_document)
    @php
        $document_data_affected_document = $document->data_affected_document;

    $number = (property_exists($document_data_affected_document,'number'))?$document_data_affected_document->number:null;
    $series = (property_exists($document_data_affected_document,'series'))?$document_data_affected_document->series:null;
    $document_type_id = (property_exists($document_data_affected_document,'document_type_id'))?$document_data_affected_document->document_type_id:null;

    @endphp
    @if($number !== null && $series !== null && $document_type_id !== null)

        @php
            $documentType  = App\Models\Tenant\Catalogs\DocumentType::find($document_type_id);
            $textDocumentType = $documentType->getDescription();
        @endphp
        <table class="full-width border-box">
            <tr>
                <td class="text-bold border-bottom font-bold">{{$textDocumentType}}</td>
            </tr>
            <tr>
                <td>{{$series }}-{{$number}}</td>
            </tr>
        </table>
    @endif
@endif
@if ($document->reference_order_form_id)
    <table class="full-width border-box">
        @if($document->order_form)
            <tr>
                <td class="text-bold border-bottom font-bold">ORDEN DE PEDIDO</td>
            </tr>
            <tr>
                <td>{{ ($document->order_form) ? $document->order_form->number_full : "" }}</td>
            </tr>
        @endif
    </table>

@elseif ($document->order_form_external)
    <table class="full-width border-box">
        <tr>
            <td class="text-bold border-bottom font-bold">ORDEN DE PEDIDO</td>
        </tr>
        <tr>
            <td>{{ $document->order_form_external }}</td>
        </tr>
    </table>

@endif


@if ($document->reference_sale_note_id)
    <table class="full-width border-box">
        @if($document->sale_note)
            <tr>
                <td class="text-bold border-bottom font-bold">NOTA DE VENTA</td>
            </tr>
            <tr>
                <td>{{ ($document->sale_note) ? $document->sale_note->number_full : "" }}</td>
            </tr>
        @endif
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
@if ($document->terms_condition)
    <br>
    <table class="full-width">
        <tr>
            <td>
                <h6 style="font-size: 12px; font-weight: bold;">Términos y condiciones del servicio</h6>
                {!! $document->terms_condition !!}
            </td>
        </tr>
    </table>
@endif

</body>
</html>
