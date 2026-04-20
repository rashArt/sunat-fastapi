@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $tittle = $document->prefix.'-'.str_pad($document->id, 8, '0', STR_PAD_LEFT);

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configuration_decimal_quantity = App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationDecimalQuantity();
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
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
                <h5 class="text-center">COTIZACIÓN</h5>
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
                <h5 class="text-center">COTIZACIÓN</h5>
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
                        <td class="font-sm" width="100px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $fullAddress }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="font-sm" width="100px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm text-muted">
                            No disponible
                        </td>
                    </tr>
                @endif
                @if ($document->account_number)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>N° Cuenta</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->account_number }}
                    </td>
                </tr>
                @endif
                @if ($customer->telephone)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Teléfono</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->telephone }}
                    </td>
                </tr>
                @endif
                @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Vendedor</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        @if ($document->seller->name)
                            {{ $document->seller->name }}
                        @else
                            {{ $document->user->name }}
                        @endif
                    </td>
                </tr>
                @endif
                @if ($document->contact)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Contacto</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->contact }}
                    </td>
                </tr>
                @endif
                @if ($document->phone)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Telf. Contacto</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->phone }}
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
                        {{ $document->date_of_issue->format('d-m-Y') }} / {{ $document->time_of_issue }}
                    </td>
                </tr>
                @if($document->date_of_due)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Tiempo de Validez</strong>
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
                            <strong>Tiempo de Entrega</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $document->delivery_date }}
                        </td>
                    @endif
                </tr>
                @endif
                @if ($document->payment_method_type)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>T. Pago</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->payment_method_type->description }}
                    </td>
                    @if($document->sale_opportunity)
                    <td class="font-sm" width="100px">
                        <strong>O. Venta</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->sale_opportunity->number_full }}
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
            </table>
        </td>
    </tr>                          
</table>

<table class="full-width mt-3">
    @if ($document->description)
        <tr class="border-box">
            <td width="15%" class="align-top">Observación: </td>
            <td width="85%">{!! str_replace("\n", "<br/>", $document->description) !!}</td>
        </tr>
    @endif
</table>

@if ($document->guides)
<br/>
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
    $show_brand = $document->items->contains(function ($row) {
        return !empty($row->item->brand);
    });

    $show_model = $document->items->contains(function ($row) {
        return !empty($row->item->model);
    });

    $show_lot = $document->items->contains(function ($row) {
        return !empty($row->getSaleLotGroupCodeDescription());
    });

    $show_due = $document->items->contains(function ($row) {
        return !empty(optional($row->relation_item)->date_of_due);
    });
@endphp

<table class="full-width mt-0 mb-0">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">COD.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">DESCRIPCIÓN</th>
        @if($show_model)
            <th class="border-top-bottom text-center py-1 desc cell-solid">MODELO</th>
        @endif

        @if($show_brand)
            <th class="border-top-bottom text-center py-1 desc cell-solid">MARCA</th>
        @endif

        @if($show_lot)
            <th class="border-top-bottom text-center py-1 desc cell-solid">LOTE</th>
        @endif

        @if($show_due)
            <th class="border-top-bottom text-center py-1 desc cell-solid">F. VENC.</th>
        @endif
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">P.UNIT</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">DTO.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="12%">TOTAL</th>
    </tr>
    </thead>
    @php
        $colspan_total = 6;

        if($show_model) $colspan_total++;
        if($show_brand) $colspan_total++;
        if($show_lot) $colspan_total++;
        if($show_due) $colspan_total++;
    @endphp
    <tbody>
    @foreach($document->items as $row)
        <tr>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->item->internal_id }}</td>
            <td class="text-center align-top desc cell-solid-rl p-1">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->item->unit_type_id }}</td>
            <td class="text-left align-top desc cell-solid-rl p-1">
                @if($row->name_product_pdf)
                    {!!$row->name_product_pdf!!}
                @else
                    {!!$row->item->description!!}
                @endif

                @if($row->total_isc > 0)
                    <br/><span style="font-size: 9px">ISC : {{ $row->total_isc }} ({{ $row->percentage_isc }}%)</span>
                @endif

                @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif

                @if($row->total_plastic_bag_taxes > 0)
                    <br/><span style="font-size: 9px">ICBPER : {{ $row->total_plastic_bag_taxes }}</span>
                @endif

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

                @if($row->charges)
                    @foreach($row->charges as $charge)
                        <br/><span style="font-size: 9px">{{ $document->currency_type->symbol}} {{ $charge->amount}} ({{ $charge->factor * 100 }}%) {{$charge->description }}</span>
                    @endforeach
                @endif

                @if($row->item->is_set == 1)
                    <br>
                    @inject('itemSet', 'App\Services\ItemSetService')
                    @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                        {{$item}}<br>
                    @endforeach
                @endif

                @if($row->item !== null && property_exists($row->item,'extra_attr_value') && $row->item->extra_attr_value != '')
                    <br/><span style="font-size: 9px">{{$row->item->extra_attr_name}}: {{ $row->item->extra_attr_value }}</span>
                @endif

                @if($row->item->used_points_for_exchange ?? false)
                    <br>
                    <span
                        style="font-size: 9px">*** Canjeado por {{$row->item->used_points_for_exchange}}  puntos ***</span>
                @endif

                @if($document->has_prepayment)
                    <br>
                    *** Pago Anticipado ***
                @endif
            </td>
            @if($show_model)
                <td class="text-left align-top desc cell-solid-rl p-1">{{ $row->item->model ?? '' }}</td>
            @endif

            @if($show_brand)
                <td class="text-left align-top desc cell-solid-rl p-1">{{ $row->item->brand ?? '' }}</td>
            @endif

            @if($show_lot)
                <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->getSaleLotGroupCodeDescription() }}</td>
            @endif

            @if($show_due)
                <td class="text-center align-top desc cell-solid-rl p-1">
                    @if(isset($row->relation_item->date_of_due))
                        {{ $row->relation_item->date_of_due->format('d-m-Y') }}
                    @else
                        -
                    @endif
                </td>
            @endif
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
        $allowed_items = 20;
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
        @if($show_model)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($show_brand)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($show_lot)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
        @if($show_due)<td class="text-center align-top desc cell-solid-rl p-1"></td>@endif
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
                <td colspan="{{ $colspan_total }}" class="p-1 text-right align-top desc cell-solid font-bold">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
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
<br>
<table class="full-width">
<tr>
    <td>
    <strong>PAGOS:</strong> </td></tr>
        @php
            $payment = 0;
        @endphp
        @foreach($document->payments as $row)
            <tr><td>- {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment }}</td></tr>
            @php
                $payment += (float) $row->payment;
            @endphp
        @endforeach
        <tr><td><strong>SALDO:</strong> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td>
    </tr>
</table>
</body>
</html>