@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $accounts = \App\Models\Tenant\BankAccount::all();
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
        top: 25%;
        left: 10%;
        width: 80%;
        height: 300px;
        text-align: center;
    ">
        <img style="width: 100%; height: auto; object-fit: contain; opacity: 0.1;"
             src="data:{{ mime_content_type(public_path($logo)) }};base64,{{ base64_encode(file_get_contents(public_path($logo))) }}"
             alt="{{ $company->name }}">
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
                <h5 class="text-center">PEDIDO</h5>
                <h3 class="text-center">{{ $tittle }}</h3>
            </td>
        @else
            <td width="70%" class="pl-1">
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
                <h5 class="text-center">PEDIDO</h5>
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
                        <strong>Cliente</strong>
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
                @if ($customer->telephone)
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Teléfono</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->telephone }}
                    </td>
                </tr>
                @endif
                
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
                        {{ $document->date_of_issue->format('d-m-Y') }}
                    </td>
                </tr>
                @if($document->date_of_due)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>F. de vencimiento</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->date_of_due->format('d-m-Y') }}
                    </td>
                </tr>
                @endif
                @if ($customer->address !== '')
                    <tr>
                        @if($document->delivery_date)
                        <td class="font-sm" width="100px">
                            <strong>Fecha de entrega</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $document->delivery_date->format('d-m-Y') }}
                        </td>
                        @endif
                    </tr>
                @endif
                @if ($document->shipping_address)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Dir. Envío</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->shipping_address }}
                    </td>
                </tr>
                @endif

                @if ($document->payment_method_type)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>T. de pago</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->payment_method_type->description }}
                    </td>
                </tr>
                @endif

                @if ($document->guides)
                    <tr>
                        @foreach($document->guides as $guide)
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
                        @endforeach
                    </tr>                    
                @endif

            </table>
        </td>       
    </tr>
</table>
@if ($document->additional_data)
    <table class="full-width mt-3">
        @foreach($document->additional_data as $row)
            <tr class="border-box">
                <td width="15%" class="align-top font-bold">{{$row->title}}: </td>
                <td width="85%" class="text-left">{{ $row->description }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table class="full-width mt-3">
    @if ($document->observation)
        <tr class="border-box">
            <td width="15%" class="align-top font-bold">Observación: </td>
            <td width="85%" class="text-left">{{ $document->observation }}</td>
        </tr>
    @endif
</table>

{{-- <table class="full-width mt-3">
    @if ($document->purchase_order)
        <tr>
            <td width="25%">Orden de Compra: </td>
            <td>:</td>
            <td class="text-left">{{ $document->purchase_order }}</td>
        </tr>
    @endif
</table> --}}

@php
    $quantity_items = $document->items()->count();
    $allowed_items = 10;
    $cycle_items = $allowed_items - ($quantity_items * 1);
@endphp

@php
$showModelColumn = false;
$showBrandColumn = false;

foreach ($document->items as $row) {
    if (!empty($row->relation_item->model)) {
        $showModelColumn = true;
    }
    if (!empty($row->relation_item->brand->name ?? null)) {
        $showBrandColumn = true;
    }
    if ($showModelColumn && $showBrandColumn) break;
}
@endphp

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-left py-1 desc cell-solid">DESCRIPCIÓN</th>
        @php
            $showSerieColumn = false;
            $showLoteColumn = false;

            foreach ($document->items as $row) {
                if ($row->item->lots) {
                    $showSerieColumn = true;
                    break;
                }
            }
        
            foreach ($document->items as $row) {
                if ($row->getSaleLotGroupCodeDescription()) {
                    $showLoteColumn = true;
                    break;
                }
            }
        @endphp        
        @if($showSerieColumn) <th class="border-top-bottom text-center py-1 desc cell-solid"> SERIE </th> @endif  
        @if($showModelColumn)
            <th class="border-top-bottom text-center py-1 desc cell-solid">MODELO</th>
        @endif
        @if($showBrandColumn)
            <th class="border-top-bottom text-center py-1 desc cell-solid">MARCA</th>
        @endif    
        @if($showLoteColumn) <th class="border-top-bottom text-center py-1 desc cell-solid"> LOTE </th> @endif
        @if($showLoteColumn) <th class="border-top-bottom text-center py-1 desc cell-solid"> F. VENC. </th> @endif
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">P.UNIT</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">DTO.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @php
        $colspan_total = 5;
        if($showSerieColumn) $colspan_total++;
        if($showLoteColumn) $colspan_total += 2;
        if($showModelColumn) $colspan_total++;
        if($showBrandColumn) $colspan_total++;
    @endphp
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
            <td class="text-left align-top desc cell-solid-rl p-1">
                {!!$row->getTemplateDescription()!!} @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
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
            @if($showSerieColumn) <td class="text-center align-top desc cell-solid-rl p-1">
                @isset($row->item->lots)
                    @foreach($row->item->lots as $lot)
                        @if( isset($lot->has_sale) && $lot->has_sale)
                            <span style="font-size: 9px">{{ $lot->series }}</span><br>
                        @endif
                    @endforeach
                @endisset
            </td> @endif
            @if($showModelColumn)
                <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->relation_item->model ?? '' }}</td>
            @endif

            @if($showBrandColumn)
                <td class="text-center  align-top">
                    {{ $row->m_item->brand->name ?? '-' }}
                </td>
            @endif
            @if($showLoteColumn) <td class="text-center align-top desc cell-solid-rl p-1">
                @if($row->getSaleLotGroupCodeDescription())
                    {{ $row->getSaleLotGroupCodeDescription() }}
                @else
                    -
                @endif
            </td> @endif
            @if($showLoteColumn) <td class="text-center align-top desc cell-solid-rl p-1">
                @if($showLoteColumn)
                    @if(isset($row->relation_item->date_of_due))
                        {{ $row->relation_item->date_of_due->format('d-m-Y') }}
                    @else
                        -
                    @endif
                @endif
            </td> @endif
            <td class="text-center align-top desc cell-solid-rl p-1">{{ number_format($row->unit_price, 2) }}</td>
            <td class="text-center align-top desc desc cell-solid-rl p-1">
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
    @for($i = 0; $i < $cycle_items; $i++)
    <tr>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-left align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        <td class="text-center align-top desc cell-solid-rl p-1"></td>
        @if($showSerieColumn)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($showModelColumn)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($showBrandColumn)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($showLoteColumn)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($showLoteColumn)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
    </tr>
    @endfor
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        @if($document->total_taxed > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif
        @if($document->total_discount > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">DESCUENTO TOTAL: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total, 2) }}</td>
        </tr>
    </tbody>
</table>
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
