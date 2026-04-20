@php
    $establishment = $document->establishment;
    $supplier = $document->supplier;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $tittle = $document->prefix.'-'.str_pad($document->id, 8, '0', STR_PAD_LEFT);
@endphp
<html>
<head>
    {{--<title>{{ $tittle }}</title>--}}
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
                    <img src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}" alt="{{$company->name}}" class="company_logo" style="max-width: 150px;">
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
                <h5 class="text-center">ORDEN DE COMPRA</h5>
                <h3 class="text-center">{{ $tittle }}</h3>
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
                <h5 class="text-center">ORDEN DE COMPRA</h5>
                <h3 class="text-center">{{ $tittle }}</h3>
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
                        <strong>Proveedor</strong>
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
                @if ($document->shipping_address)                
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Dir. Envío</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->shipping_address }}
                    </td>
                </tr>
                @endif
                @if ($supplier->telephone)
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Teléfono</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $supplier->telephone }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Vendedor</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->user->name }}
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
                        {{ $document->date_of_issue->format('Y-m-d') }}
                    </td>
                </tr>
                @if($document->date_of_due)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Fecha de vencimiento</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">{{ $document->date_of_due->format('Y-m-d') }}</td>
                </tr>
                @endif
                @if ($document->payment_method_type)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>T. Pago</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">{{ $document->payment_method_type->description }}</td>
                </tr>
                @endif
                @if($document->sale_opportunity)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>O. Venta</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">{{ $document->sale_opportunity->number_full }}</td>
                </tr>
                @endif

                @if ($document->guides)
                    @foreach($document->guides as $guide)
                    <tr>
                        <td class="font-sm" width="100px">
                            <strong>
                                @if(isset($guide->document_type_description))
                                    {{ $guide->document_type_description }}
                                @else
                                    {{ $guide->document_type_id }}
                                @endif
                            </strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $guide->number }}
                        </td>
                    </tr>
                    @endforeach
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="full-width mt-3">
    @if ($document->purchase_quotation)
        <tr class="border-box">
            <td width="15%" class="align-top">Proforma: </td>
            <td width="85%">{{ $document->purchase_quotation->identifier }}</td>
        </tr>
    @endif
</table>
<table class="full-width mt-3">
    @if ($document->description)
        <tr class="border-box">
            <td width="15%" class="align-top">Descripción: </td>
            <td width="85%">{{ $document->description }}</td>
        </tr>
    @endif
</table>



<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">DESCRIPCIÓN</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">MODELO</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">P.UNIT</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">DTO.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        <tr>
            <td class="text-center align-top desc cell-solid-rl p-1">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->item->unit_type_id }}</td>
            <td class="text-left align-top text-upp p-1 desc cell-solid-rl">
                {!!$row->item->description!!} @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
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
            </td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->item->model ?? '' }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ number_format($row->unit_price, 2) }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">
                @if($row->discounts)
                    @php
                        $total_discount_line = 0;
                        foreach ($row->discounts as $disto) {
                            $total_discount_line = $total_discount_line + $disto->amount;
                        }
                    @endphp
                    {{ number_format($total_discount_line, 2) }}
                @else
                0
                @endif
            </td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ number_format($row->total, 2) }}</td>
        </tr>
    @endforeach
    @php
        $quantity_items = $document->items()->count();
        $allowed_items = 40;
        $cycle_items = $allowed_items - ($quantity_items * 1);
    @endphp
    @for($i = 0; $i < $cycle_items; $i++)
    <tr>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-left align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
    </tr>
    @endfor
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        @if($document->total_taxed > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif
        @if($document->total_discount > 0)
            <tr>
                <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="p-1 text-right align-top desc cell-solid font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total, 2) }}</td>
        </tr>
    </tbody>
</table>
<table class="full-width">
    <tr>
        {{-- <td width="65%">
            @foreach($document->legends as $row)
                <p>Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></p>
            @endforeach
            <br/>
            <strong>Información adicional</strong>
            @foreach($document->additional_information as $information)
                <p>@if(\App\CoreFacturalo\Helpers\Template\TemplateHelper::canShowNewLineOnObservation())
                            {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::SetHtmlTag($information) !!}
                        @else
                            {{$information}}
                        @endif</p>
            @endforeach
        </td> --}}
    </tr>
</table>
</body>
</html>
