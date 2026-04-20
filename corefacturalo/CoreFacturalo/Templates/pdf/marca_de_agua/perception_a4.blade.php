@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    $path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');

    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
@endphp
<html>
<head>
    <title>{{ $document_number }}</title>
    <link href="{{ $path_style }}" rel="stylesheet" />
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
                <img src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}" alt="{{$company->name}}" alt="{{ $company->name }}" class="company_logo" style="max-width: 200px">
            </td>
            <td width="50%" class="text-center">
                <div class="text-left">
                    <h3 class="">{{ $company->name }}</h3>
                    <h4>{{ 'RUC '.$company->number }}</h4>
                    <h5>{{ ($establishment->address !== '-')? $establishment->address : '' }}</h5>
                    <h5>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h5>
                    <h5>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h5>
                </div>
            </td>
            <td width="40%" class="border-box p-3 text-center">
                <h4 class="text-center">{{ $document->document_type->description }}</h4>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @else
        <td width="60%" class="pl-1">
            <div class="text-left">
                <h3 class="">{{ $company->name }}</h3>
                <h4>{{ 'RUC '.$company->number }}</h4>
                <h5>{{ ($establishment->address !== '-')? $establishment->address : '' }}</h5>
                <h5>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h5>
                <h5>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h5>
            </div>
        </td>
        <td width="40%" class="border-box p-3 text-center">
            <h4 class="text-center">{{ $document->document_type->description }}</h4>
            <h3 class="text-center">{{ $document_number }}</h3>
        </td>
        @endif        
    </tr>
</table>
<table class="full-width mt-5">
    <tr>
        <td width="47%" class="border-box pl-3 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Señor(es)</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->name }}
                    </td>  
                </tr>
                <tr>
                    <td class="font-sm" width="80px">
                        <strong> {{ $customer->identity_document_type->description }} </strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->number }}
                    </td>
                </tr>
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Moneda</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->currency_type->description }}
                    </td>
                </tr>
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Dirección</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->address }}
                    </td> 
                </tr>
            </table>
        </td>
        <td width="3%"></td>
        <td width="50%" class="border-box pl-3 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Fecha de emisión</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->date_of_issue->format('d/m/Y') }}
                    </td>  
                </tr>
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Régimen de percepción</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->perception_type->description }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Tipo<br/>Comprobante</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Número<br/>Comprobante</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Fecha de<br/>Emisión</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Moneda<br/>Comprobante</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Total<br/>Comprobante</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Tasa %</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Importe<br/>Percibido</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">Tipo<br/>Cambio</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->documents as $row)
        <tr>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->document_type->short }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->series }}-{{ $row->number }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->date_of_issue->format('d/m/Y') }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->currency_type_id }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->total_document }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $document->perception_type->percentage }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->total_perception }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->exchange_rate->factor }}</td>
        </tr>
    @endforeach

    @php
        $totalProductos = count($document->documents);
        $cycle_items = 40 - $totalProductos;
    @endphp
    @for($i = 0; $i < $cycle_items; $i++)
        <tr>
            <td class="text-center align-top desc cell-solid-rl p-1"></td>
            <td class="text-center align-top desc cell-solid-rl p-1"></td>
            <td class="text-center align-top desc cell-solid-rl p-1"></td>
            <td class="text-center align-top desc cell-solid-rl p-1"></td>
            <td class="text-right align-top desc cell-solid-rl p-1"></td>
            <td class="text-center align-top desc cell-solid-rl p-1"></td>
            <td class="text-right align-top desc cell-solid-rl p-1"></td>
            <td class="text-right align-top desc cell-solid-rl p-1"></td>
        </tr>
    @endfor
    </tbody>
    <tfoot>
    <tr class="border-box">
        <td class="border-top text-right" colspan="4">Totales({{ $document->currency_type->symbol }})</td>
        <td class="border-top text-right">{{ $document->total }}</td>
        <td class="border-top"></td>
        <td class="border-top text-right">{{ $document->total_perception }}</td>
        <td class="border-top"></td>
    </tr>
    </tfoot>
</table>
<table class="full-width">
    @if($document->hash)
        <tr>
            <td>Código Hash: {{ $document->hash }}</td>
        </tr>
    @endif
    @foreach($document->legends as $row)
        <tr>
            <td class="font-bold">{{ $row->value }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>