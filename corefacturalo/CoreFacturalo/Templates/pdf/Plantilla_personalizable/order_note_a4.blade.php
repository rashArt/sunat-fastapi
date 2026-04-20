@php
/** @var \App\Models\Tenant\Document $document */
    $establishment = $document->establishment;
    $customer = $document->customer;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $accounts = \App\Models\Tenant\BankAccount::all();
    $tittle = $document->prefix.'-'.str_pad($document->id, 8, '0', STR_PAD_LEFT);

    // Obtener configuración de columnas para Plantilla_personalizable
    $columnsConfig = \App\Models\Tenant\TemplateColumnsConfig::where('establishment_id', $document->establishment_id)
        ->where('template_name', 'Plantilla_personalizable')
        ->first();

    $showColumns = $columnsConfig ? $columnsConfig->columns_config : [
        'codigo' => true,
        'cantidad' => true,
        'unidad' => true,
        'descripcion' => true,
        'serie' => false,
        'modelo' => false,
        'marca' => false,
        'lote' => false,
        'fecha_vencimiento' => false,
        'precio_unitario' => true,
        'descuento' => true,
        'total' => true,
    ];
@endphp
<html>
<head>
    {{--<title>{{ $tittle }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>
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
        <td width="15%">Cliente:</td>
        <td width="45%">{{ $customer->name }}</td>
        <td width="25%">Fecha de emisión:</td>
        <td width="15%">{{ $document->date_of_issue->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td>{{ $customer->identity_document_type->description }}:</td>
        <td>{{ $customer->number }}</td>
        @if($document->date_of_due)
            <td width="25%">Fecha de vencimiento:</td>
            <td width="15%">{{ $document->date_of_due->format('Y-m-d') }}</td>
        @endif
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

    <tr>
        <td class="align-top">Dirección:</td>
        <td>
            {{ $fullAddress ?: 'No disponible' }}
        </td>
        @if($document->delivery_date)
            <td width="25%">Fecha de entrega:</td>
            <td width="15%">{{ $document->delivery_date->format('Y-m-d') }}</td>
        @endif
    </tr>
    @if ($document->shipping_address)
    <tr>
        <td class="align-top">Dir. Envío:</td>
        <td colspan="3">
            {{ $document->shipping_address }}
        </td>
    </tr>
    @endif
    @if ($customer->telephone)
    <tr>
        <td class="align-top">Teléfono:</td>
        <td colspan="3">
            {{ $customer->telephone }}
        </td>
    </tr>
    @endif
    @if ($document->payment_method_type)
    <tr>
        <td class="align-top">T. Pago:</td>
        <td colspan="3">
            {{ $document->payment_method_type->description }}
        </td>
    </tr>
    @endif
    <tr>
        <td class="align-top">Vendedor:</td>
        <td colspan="3">
            {{ $document->user->name }}
        </td>
    </tr>
</table>

@if ($document->additional_data)
    <table class="full-width">
        @foreach($document->additional_data as $row)
            <tr>
                <td width="15%" class="align-top">{{$row->title}}: </td>
                <td width="85%">{{ $row->description }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table class="full-width mt-3">
    @if ($document->observation)
        <tr>
            <td width="15%" class="align-top">Observación: </td>
            <td width="85%">{{ $document->observation }}</td>
        </tr>
    @endif
</table>

@if ($document->guides)
<br/>
{{--<strong>Guías:</strong>--}}
<table>
    @foreach($document->guides as $guide)
        <tr>
            @if(isset($guide->document_type_description))
            <td>{{ $guide->document_type_description }}</td>
            @else
            <td>{{ $guide->document_type_id }}</td>
            @endif
            <td>:</td>
            <td>{{ $guide->number }}</td>
        </tr>
    @endforeach
</table>
@endif

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
    <tr class="bg-grey">
        @if($showColumns['cantidad']) <th class="border-top-bottom text-center py-2 px-1" width="8%">CANT.</th> @endif
        @if($showColumns['unidad']) <th class="border-top-bottom text-center py-2 px-1" width="9%">UNIDAD</th> @endif
        @if($showColumns['descripcion']) <th class="border-top-bottom text-left py-2 px-1">DESCRIPCIÓN</th> @endif
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
        @if( $showColumns['serie'] && $showSerieColumn) <th class="border-top-bottom text-left py-2 px-1"> SERIE </th> @endif  
        @if($showColumns['modelo'] && $showModelColumn)
            <th class="border-top-bottom text-left py-2 px-1">MODELO</th>
        @endif
        @if($showColumns['marca'] && $showBrandColumn)
            <th class="border-top-bottom text-center py-2 px-1">MARCA</th>
        @endif    
        @if( $showColumns['lote'] && $showLoteColumn) <th class="border-top-bottom text-center py-2 px-1"> LOTE </th> @endif
        @if($showColumns['fecha_vencimiento'] && $showLoteColumn) <th class="border-top-bottom text-center py-2 px-1"> F. VENC. </th> @endif
        @if($showColumns['precio_unitario']) <th class="border-top-bottom text-right py-2 px-1 col-total">P.UNIT</th> @endif
        @if($showColumns['descuento']) <th class="border-top-bottom text-right py-2 px-1" width="8%">DTO.</th> @endif
        @if($showColumns['total']) <th class="border-top-bottom text-right py-2 px-1 col-total">TOTAL</th> @endif
    </tr>
    </thead>
    <tbody>
        @php
            $colspan_total = 0;
                
            if($showColumns['cantidad']) $colspan_total++;
            if($showColumns['unidad']) $colspan_total++;
            if($showColumns['descripcion']) $colspan_total++;
            if($showColumns['serie'] && $showSerieColumn) $colspan_total++;
            if($showColumns['modelo'] && $showModelColumn) $colspan_total++;
            if($showColumns['marca'] && $showBrandColumn) $colspan_total++;
            if($showColumns['lote'] && $showLoteColumn) $colspan_total++;
            if($showColumns['fecha_vencimiento'] && $showLoteColumn) $colspan_total++;
            if($showColumns['precio_unitario']) $colspan_total++;
            if($showColumns['descuento']) $colspan_total++;
            if($showColumns['total']) $colspan_total++;

        @endphp

        @foreach($document->items as $row)
        @php
        /** @var \Modules\Order\Models\OrderNoteItem $row */
        $row = $row;
        $item = $row->item;
        @endphp
        <tr>
            @if($showColumns['cantidad']) <td class="text-center align-top"> {{ $row->getStringQty() }} </td> @endif
            @if($showColumns['unidad']) <td class="text-center align-top">{{ $item->unit_type_id }}</td> @endif
            @if($showColumns['descripcion']) <td class="text-left">
                {!!$row->getTemplateDescription() !!}
                @if (!empty($item->presentation)) {!!$item->presentation->description!!} @endif
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
            </td> @endif
            @if($showColumns['serie'] && $showSerieColumn)
            <td class="text-left align-top">
                @isset($row->item->lots)
                    @foreach($row->item->lots as $lot)
                        @if(isset($lot->has_sale) && $lot->has_sale)
                            <span class="badge badge-secondary d-inline-block mb-1" style="font-size: 9px;">
                                {{ $lot->series }}
                            </span><br>
                        @endif
                    @endforeach
                @endisset
            </td>
            @endif
            @if($showColumns['modelo'] && $showModelColumn)
                <td class="text-left">{{ $row->relation_item->model ?? '' }}</td>
            @endif

            @if($showColumns['marca'] && $showBrandColumn)
                <td class="text-left align-top">
                    {{ $row->m_item->brand->name ?? '' }}
                </td>
            @endif
            @if($showColumns['lote'] && $showLoteColumn) <td class="text-center align-top">
                {{ $row->getSaleLotGroupCodeDescription() }}
            </td> @endif
            @if($showColumns['fecha_vencimiento'] && $showLoteColumn) <td class="text-center align-top">
                @if($showLoteColumn)
                    @if(isset($row->relation_item->date_of_due))
                        {{ $row->relation_item->date_of_due->format('Y-m-d') }}
                    @else
                        -
                    @endif
                @endif
            </td> @endif
            @if($showColumns['precio_unitario']) <td class="text-right align-top">{{ $row->getStringUnitPrice()}}</td> @endif
            @if($showColumns['descuento']) <td class="text-right align-top">
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
            </td> @endif
            @if($showColumns['total']) <td class="text-right align-top">{{ $row->getStringTotal() }}</td> @endif
        </tr>
        <tr>
            <td colspan="{{ $colspan_total }}" class="border-bottom"></td>
        </tr>
    @endforeach
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        @if($document->total_taxed > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif
        @if($document->total_discount > 0)
            <tr>
                <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">DESCUENTO TOTAL: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan_total - 1 }}" class="text-right font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total, 2) }}</td>
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
