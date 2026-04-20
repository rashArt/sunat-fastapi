@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $supplier = $document->supplier;
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configurationInPdf = App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
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
            <td width="20%">
                <div class="company_logo_box">
                    <img src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}" alt="{{$company->name}}" class="company_logo" style="max-width: 150px;">
                </div>
            </td>
            <td width="50%" class="text-center">
                <div class="text-left">
                    <h4 class="">{{ $company->name }}</h4>
                    <h5>{{ 'RUC '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset
                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">{{ $document->document_type->description }}</h5>
                <h3 class="text-center">{{ $document_number }}</h3>
            </td>
        @else
            <td width="50%" class="pl-1">
                <div class="text-left">
                    <h4 class="">{{ $company->name }}</h4>
                    <h5>{{ 'RUC '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset
                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">{{ $document->document_type->description }}</h5>
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
                        {{ $supplier->name }}
                    </td>
                </tr>
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>{{ $supplier->identity_document_type->description }}</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $supplier->number }}
                    </td>
                </tr>
                @php
                    $addressParts = [];

                    if (!empty($customer->address)) {
                        $addressParts[] = $customer->address;
                    }
                
                    if (!empty($customer->district_id) && $customer->district_id !== '-' && isset($customer->district) && !empty($customer->district->description)) {
                        $addressParts[] = $customer->district->description;
                    }
                
                    if (!empty($customer->province_id) && $customer->province_id !== '-' && isset($customer->province) && !empty($customer->province->description)) {
                        $addressParts[] = $customer->province->description;
                    }
                
                    if (!empty($customer->department_id) && $customer->department_id !== '-' && isset($customer->department) && !empty($customer->department->description)) {
                        $addressParts[] = $customer->department->description;
                    }
                
                    $fullAddress = implode(', ', $addressParts);
                @endphp

                @if ($fullAddress)
                    <tr>
                        <td class="font-sm" width="80px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $fullAddress }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="font-sm" width="80px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm text-muted">
                            No disponible
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Régimen</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->retention_type->description }}
                    </td>
                </tr>
            </table>
        </td>
        <td width="3%"></td>
        <td width="50%" class="border-box pl-3 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Fecha emisión</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->date_of_issue->format('Y-m-d') }}
                    </td>
                </tr>
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Moneda</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->currency_type_id }}
                    </td>
                </tr>
                @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Responsable</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->user->name }}
                    </td>
                </tr>
                @endif
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
        <th class="border-top-bottom text-center py-1 desc cell-solid">Importe<br/>Retención</th>
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
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $document->retention_type->percentage }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->total_retention }}</td>
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
        <td class="border-top text-right">{{ $document->total_retention }}</td>
        <td class="border-top"></td>
    </tr>
    </tfoot>
</table>

@if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
<table class="full-width">
    <tr>
        <td width="65%" style="text-align: top; vertical-align: top;">
            <br>
            @foreach($accounts as $account)
                <p>
                <span class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                <span class="font-bold">N°:</span> {{$account->number}}
                @if($account->cci)
                - <span class="font-bold">CCI:</span> {{$account->cci}}
                @endif
                </p>
            @endforeach
        </td>
    </tr>
</table>
@endif

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