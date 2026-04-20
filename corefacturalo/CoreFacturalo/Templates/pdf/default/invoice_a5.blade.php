@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    $document_base = ($document->note) ? $document->note : null;

    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    // $accounts = \App\Models\Tenant\BankAccount::where('show_in_documents', true)->get();
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    if($document_base) {
        $affected_document_number = ($document_base->affected_document) ? $document_base->affected_document->series.'-'.str_pad($document_base->affected_document->number, 8, '0', STR_PAD_LEFT) : $document_base->data_affected_document->series.'-'.str_pad($document_base->data_affected_document->number, 8, '0', STR_PAD_LEFT);

    } else {
        $affected_document_number = null;
    }

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $payments = $document->payments;
    $document->load('reference_guides');

    $total_payment = $document->payments->sum('payment');
    $balance = ($document->total - $total_payment) - $document->payments->sum('change');
    $configuration_decimal_quantity = App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationDecimalQuantity();

    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>
@if($document->state_type->id == '11')
    <div class="company_logo_box" style="position: absolute; text-align: center; top:50%;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png")))}}"
            alt="anulado" class="" style="opacity: 0.6;">
    </div>
@endif
@if($document->state_type->id == '09')
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))}}"
            alt="rechazado" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
@if(isset($configuration['is_preview']) && $configuration['is_preview'])
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png")))}}"
            alt="vista previa" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
<table class="full-width">
    <tr>
        @if($company->logo)
            <td width="20%">
                <div class="company_logo_box">
                    <img
                        src="data:{{ mime_content_type(public_path($logo)) }};base64, {{ base64_encode(file_get_contents(public_path($logo))) }}"
                        alt="{{ $company->name }}" class="company_logo" style="max-width: 150px;">
                </div>
            </td>
            <td width="50%" class="pl-3 text-center">
                <div>
                    <h4>{{ $company->name }}</h4>
                    <h5>{{ 'RUC '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-') ? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-') ? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-') ? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-') ? '- '.$establishment->department->description : '' }}
                    </h6>
                    @isset($establishment->trade_address)
                        <h6>{{ $establishment->trade_address !== '-' ? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset
                    <h6>{{ $establishment->telephone !== '-' ? 'Central telefónica: '.$establishment->telephone : '' }}</h6>
                    <h6>{{ $establishment->email !== '-' ? 'Email: '.$establishment->email : '' }}</h6>
                    @isset($establishment->web_address)
                        <h6>{{ $establishment->web_address !== '-' ? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset
                    @isset($establishment->aditional_information)
                        <h6>{{ $establishment->aditional_information !== '-' ? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h3 class="font-bold">{{ 'R.U.C. '.$company->number }}</h3>
                <h5>{{ $document->document_type->description }}</h5>
                <h3>{{ $document_number }}</h3>
            </td>
        @else
            <td colspan="2" width="70%" class="pl-1 text-left">
                <div>
                    <h4>{{ $company->name }}</h4>
                    <h5>{{ 'RUCs '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-') ? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-') ? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-') ? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-') ? '- '.$establishment->department->description : '' }}
                    </h6>
                    @isset($establishment->trade_address)
                        <h6>{{ $establishment->trade_address !== '-' ? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset
                    <h6>{{ $establishment->telephone !== '-' ? 'Central telefónica: '.$establishment->telephone : '' }}</h6>
                    <h6>{{ $establishment->email !== '-' ? 'Email: '.$establishment->email : '' }}</h6>
                    @isset($establishment->web_address)
                        <h6>{{ $establishment->web_address !== '-' ? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset
                    @isset($establishment->aditional_information)
                        <h6>{{ $establishment->aditional_information !== '-' ? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h3 class="font-bold">{{ 'R.U.C. '.$company->number }}</h3>
                <h5>{{ $document->document_type->description }}</h5>
                <h3>{{ $document_number }}</h3>
            </td>
        @endif
    </tr>
</table>
<table class="full-width mt-2">
    <tr>
        <td width="20%">CLIENTE:</td>
        <td width="50%">
            {{ $customer->name }}
            @if ($customer->internal_code ?? false)
                <br>
                <small>{{ $customer->internal_code ?? '' }}</small>
            @endif
        </td>
        <td>FECHA DE EMISIÓN:</td>
        <td>{{$document->date_of_issue->format('Y-m-d')}}</td>
    </tr>
    <tr>
        <td>{{ $customer->identity_document_type->description }}:</td>
        <td>{{$customer->number}}</td>
        <td>FECHA VENC.:</td>
        <td>
            @if($invoice)
                {{$invoice->date_of_due->format('Y-m-d')}}
            @endif
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

    <tr>
        <td class="align-top">DIRECCIÓN:</td>
        <td>
            {{ $fullAddress ?: 'No disponible' }}
        </td>
    </tr>
    <tr>
        <td class="align-top">MONEDA:</td>
        <td>
            @if($document->currency_type_id == 'PEN')
            Soles
            @elseif($document->currency_type_id == 'USD')
            Dolares
            @endif
        </td>
    </tr>    
    @if ($document->detraction)
        <tr>
            @inject('detractionType', 'App\Services\DetractionTypeService')
            <td>N. CTA DETRACCIONES:</td>
            <td class="align-top">{{ $document->detraction->bank_account}}</td>
            <td>B/S SUJETO A DETRACCIÓN:</td>
            <td>
                {{$document->detraction->detraction_type_id}} - {{ $detractionType->getDetractionTypeDescription($document->detraction->detraction_type_id ) }}
            </td>
        </tr>
        <tr>
            <td>MÉTODO DE PAGO:</td>
            <td>{{ $detractionType->getPaymentMethodTypeDescription($document->detraction->payment_method_id ) }}</td>
            <td>P. DETRACCIÓN:</td>
            <td>{{ $document->detraction->percentage}}%</td>
        </tr>
        <tr>
            <td>MONTO DETRACCIÓN:</td>
            <td>S/ {{ $document->detraction->amount}}</td>
            @if($document->detraction->pay_constancy)
                <td >C. PAGO:</td>
                <td>{{ $document->detraction->pay_constancy}}</td>
            @endif
        </tr>
    @endif
    @if ($document->reference_data)
        <tr>
            <td>D. REFERENCIA:</td>
            <td>{{ $document->reference_data}}</td>
            <td></td>
            <td></td>
        </tr>
    @endif
    @if ($document->plate_number !== null)
        <tr>
            <td >N° Placa:</td>
            <td >{{ $document->plate_number }}</td>
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

@if ($document->dispatch)
    <br/>
    <strong>Guías de remisión</strong>
    <table>
        <tr>
            <td>{{ $document->dispatch->number_full }}</td>
        </tr>
    </table>

@elseif ($document->reference_guides)
    @if (count($document->reference_guides) > 0)
        <br/>
        <strong>Guias de remisión</strong>
        <table>
            @foreach($document->reference_guides as $guide)
                <tr>
                    <td>{{ $guide->series }}</td>
                    <td>-</td>
                    <td>{{ $guide->number }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<table class="full-width mt-3">
    @if ($document->prepayments)
        @foreach($document->prepayments as $p)
            <tr>
                <td width="120px">ANTICIPO</td>
                <td width="8px">:</td>
                <td>{{$p->number}}</td>
            </tr>
        @endforeach
    @endif
    @if ($document->purchase_order)
        <tr>
            <td width="120px">ORDEN DE COMPRA</td>
            <td width="8px">:</td>
            <td>{{ $document->purchase_order }}</td>
        </tr>
    @endif
    @if ($document->quotation_id)
        <tr>
            <td width="120px">COTIZACIÓN</td>
            <td width="8px">:</td>
            <td>{{ $document->quotation->identifier }}</td>
            @isset($document->quotation->delivery_date)
                <td width="120px">F. ENTREGA</td>
                <td width="8px">:</td>
                <td>{{ $document->date_of_issue->addDays($document->quotation->delivery_date)->format('d-m-Y') }}</td>
            @endisset
        </tr>
    @endif
    @isset($document->quotation->sale_opportunity)
        <tr>
            <td width="120px">O. VENTA</td>
            <td width="8px">:</td>
            <td>{{ $document->quotation->sale_opportunity->number_full}}</td>
        </tr>
    @endisset
    @if(!is_null($document_base))
        <tr>
            <td width="120px">DOC. AFECTADO</td>
            <td width="8px">:</td>
            <td>{{ $affected_document_number }}</td>

            <td width="120px">TIPO DE NOTA</td>
            <td width="8px">:</td>
            <td>{{ ($document_base->note_type === 'credit')?$document_base->note_credit_type->description:$document_base->note_debit_type->description}}</td>
        </tr>
        <tr>
            <td>DESCRIPCIÓN</td>
            <td>:</td>
            <td>{{ $document_base->note_description }}</td>
        </tr>
    @endif
</table>

@php
$showBrandColumn = false;
$showModelColumn = false;

foreach ($document->items as $row) {
    if (!empty($row->item->model)) {
        $showModelColumn = true;
    }

    if (!empty($row->m_item->brand->name ?? null)) {
        $showBrandColumn = true;
    }

    if ($showModelColumn && $showBrandColumn) break;
}
@endphp

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr class="bg-grey">
        <th class="border-top-bottom text-center py-2" width="6%">COD.</th>
        <th class="border-top-bottom text-center py-2" width="6%">CANT.</th>
        <th class="border-top-bottom text-center py-2" width="7%">UNIDAD</th>
        <th class="border-top-bottom text-left py-2">DESCRIPCIÓN</th>
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
                if (isset($row->item->IdLoteSelected)) {
                    $showLoteColumn = true;
                    break;
                }
            }
        @endphp
        @empty($showSerieColumn) @else <th class="border-top-bottom text-left py-2"> SERIE </th> @endempty
        @if($showModelColumn)
            <th class="border-top-bottom text-left py-2">MODELO</th>
        @endif
        @if($showBrandColumn)
            <th class="border-top-bottom text-center py-2">MARCA</th>
        @endif
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2" width="12%">
             LOTE 
        </th> @endif
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2" width="9%"> F. VENC. </th> @endif
        <th class="border-top-bottom text-right py-2 col-total">P.UNIT</th>
        <th class="border-top-bottom text-right py-2 pr-2" width="7%">DTO.</th>
        <th class="border-top-bottom text-right py-2 col-total">TOTAL</th>
    </tr>
    </thead>
    <tbody>
        @php
            $colspan_base = 6;
            $colspan_total = $colspan_base;

            if($showSerieColumn) $colspan_total++;
            if($showModelColumn) $colspan_total++;
            if($showBrandColumn) $colspan_total++;
            if($showLoteColumn) {
                $colspan_total++;
                $colspan_total++;
            }
        @endphp
        @foreach($document->items as $row)
        <tr>
            <td class="text-center align-top">{{ $row->item->internal_id }}</td>
            <td class="text-center align-top">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center align-top">{{ $row->item->unit_type_id }}</td>
            <td class="text-left align-top">
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
                        {{-- Excluir atributos de placa (diferentes variaciones de texto) --}}
                        @if(!in_array(strtoupper(trim($attr->description)), ['PLACA', 'NRO PLACA', 'NUMERO DE PLACA', 'NÚMERO DE PLACA', 'N° PLACA']))
                            <br /><span style="font-size: 9px">{!! $attr->description !!} : {{ $attr->value }}</span>
                        @endif
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
            @empty($showSerieColumn) @else <td class="text-left align-top">
                @isset($row->item->lots)
                    @foreach($row->item->lots as $lot)
                        @if( isset($lot->has_sale) && $lot->has_sale)
                            <span style="font-size: 9px">{{ $lot->series }}</span><br>
                        @endif
                    @endforeach
                @endisset
            </td> @endempty
            @if($showModelColumn)
                <td class="text-left align-top">{{ $row->item->model ?? '' }}</td>
            @endif

            @if($showBrandColumn)
                <td class="text-left align-top">
                    {{ $row->m_item->brand->name ?? '' }}
                </td>
            @endif
            @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
            @php
                $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
                $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);
            @endphp

            @if($showLoteColumn)
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
            @if($showLoteColumn)
                <td class="text-center align-top">
                    @php
                        $cleanedDate = $date_due != ''
                            ? ltrim($date_due, '/')
                            : ($row->relation_item->date_of_due ? $row->relation_item->date_of_due->format('Y-m-d') : '');
                    @endphp
            
                    {{ $cleanedDate }}
                </td>
            @endif
            @if ($configuration_decimal_quantity->change_decimal_quantity_unit_price_pdf)
                <td class="text-right align-top">{{ $row->generalApplyNumberFormat($row->unit_price, $configuration_decimal_quantity->decimal_quantity_unit_price_pdf) }}</td>
            @else
                <td class="text-right align-top">{{ number_format($row->unit_price, 2) }}</td>
            @endif

            <td class="text-right align-top pr-2">
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
            <td class="text-right align-top">{{ number_format($row->total, 2) }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan_total+1 }}" class="border-bottom"></td>
        </tr>
    @endforeach

    @if ($document->prepayments)
        @foreach($document->prepayments as $p)
            <tr>
                <td class="text-center align-top">1</td>
                <td class="text-center align-top">NIU</td>
                <td class="text-left align-top">
                    ANTICIPO: {{($p->document_type_id == '02')? 'FACTURA':'BOLETA'}} NRO. {{$p->number}}
                </td>
                <td class="text-right align-top"></td>
                <td class="text-right align-top">-{{ number_format($p->total, 2) }}</td>
                <td class="text-right align-top">0</td>
                <td class="text-right align-top">-{{ number_format($p->total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="{{ $colspan_total+1 }}" class="border-bottom"></td>
            </tr>
        @endforeach
    @endif

    @if($document->total_exportation > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_exportation, 2) }}</td>
        </tr>
    @endif
    @if($document->total_free > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_free, 2) }}</td>
        </tr>
    @endif
    @if($document->total_unaffected > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
        </tr>
    @endif
    @if($document->total_exonerated > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
        </tr>
    @endif

    @if ($document->document_type_id === '07')
        @if($document->total_taxed >= 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif
    @elseif($document->total_taxed > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_taxed, 2) }}</td>
        </tr>
    @endif

    @if($document->total_plastic_bag_taxes > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">ICBPER: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_plastic_bag_taxes, 2) }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">IGV: {{ $document->currency_type->symbol }}</td>
        <td class="text-right font-bold">{{ number_format($document->total_igv, 2) }}</td>
    </tr>

    @if($document->total_isc > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">ISC: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_isc, 2) }}</td>
        </tr>
    @endif

    @if($document->total_discount > 0 && $document->subtotal > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">SUBTOTAL: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->subtotal, 2) }}</td>
        </tr>
    @endif

    @if($document->total_discount > 0)
        <tr>
            <td colspan="{{ $colspan_total }}"
                class="text-right font-bold pr-2">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}
                : {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_discount, 2) }}</td>
        </tr>
    @endif

    @if($document->total_charge > 0)
        @if($document->charges)
            @php
                $total_factor = 0;
                foreach($document->charges as $charge) {
                    $total_factor = ($total_factor + $charge->factor) * 100;
                }
            @endphp
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">CARGOS ({{$total_factor}}
                    %): {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @else
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">CARGOS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif
    @endif

    <tr>
        <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
        <td class="text-right font-bold">{{ number_format($document->total, 2) }}</td>
    </tr>

    @if(($document->retention || $document->detraction) && $document->total_pending_payment > 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">M. PENDIENTE: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_pending_payment, 2) }}</td>
        </tr>
    @endif

    @if($balance < 0)
        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold pr-2">VUELTO: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format(abs($balance),2, ".", "") }}</td>
        </tr>
    @endif
    </tbody>
</table>
<table class="full-width">
    @php
        $personType = $document->person?->person_type;
    @endphp
    @if ( $personType && $personType->enabled_description_person_type)
        <tr width="65%" >
            <td>
                <strong>{{ $personType->description }}</strong> : {{ $personType->description_person_type }}
            </td>
        </tr>
    @endif
    <tr>
        <td class="align-top">
            <table class="full-width">
                @foreach(array_reverse( (array) $document->legends) as $row)
                    @if ($row->code == "1000")
                        <tr style="text-transform: uppercase;">
                            <td width="25%" class="font-bold">SON:</td>
                            <td>{{ $row->value }} {{ $document->currency_type->description }}</td>
                        </tr>
                    @else
                        @if (count((array) $document->legends)>1 && $loop->first())
                            <tr>
                                <td colspan="2" class="font-bold">Leyendas</td>
                            </tr>
                        @endif
                        <tr>
                            <td width="25%">{{$row->code}}:</td>
                            <td>{{ $row->value }}</td>
                        </tr>
                    @endif
                @endforeach

                @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
                    <tr>
                        <td class="font-bold">VENDEDOR:</td>
                        <td>{{ $document->seller ? $document->seller->name : $document->user->name }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="font-bold">CONDICIÓN DE PAGO:</td>
                    <td>{{ $paymentCondition }} </td>
                </tr>
                @if($document->payment_method_type_id)
                    <tr>
                        <td class="font-bold">MÉTODO DE PAGO:</td>
                        <td>{{ $document->payment_method_type->description }}</td>
                    </tr>
                @endif
                @if($document->detraction)
                    <tr>
                        <td colspan="2">Operación sujeta al Sistema de Pago de Obligaciones Tributarias</td>
                    </tr>
                @endif
                @if ($document->payment_condition_id === '01')
                    @if($payments->count())
                        <tr>
                            <td class="font-bold"> PAGOS</td>
                            <td></td>
                        </tr>
                        @php
                            $payment = 0;
                        @endphp
                        <tr>
                            <td colspan="2">
                                <ul>
                                    @foreach($payments as $row)
                                        <li>
                                            {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment + $row->change }}
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif
                @else
                    <tr>
                        <td colspan="2">
                            <ul>
                                @foreach($document->fee as $key => $quote)
                                    <li>
                                        {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }} / Fecha: {{ $quote->date->format('d-m-Y') }} / Monto: {{ $quote->currency_type->symbol }}{{ $quote->amount }}
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
                @if ($document->terms_condition)
                    <tr>
                        <td colspan="2" class="font-bold">TÉRMINOS Y CONDICIONES DEL SERVICIO</td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            {!! $document->terms_condition !!}
                        </td>
                    </tr>
                @endif
                @foreach($document->additional_information as $information)
                    @if ($information)
                        @if ($loop->first)
                            <tr>
                                <td colspan="2" class="font-bold">INFORMACIÓN ADICIONAL</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="2">
                                @if(\App\CoreFacturalo\Helpers\Template\TemplateHelper::canShowNewLineOnObservation())
                                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::SetHtmlTag($information) !!}
                                @else
                                    {{$information}}
                                @endif</p>
                            </td>
                        </tr>
                        <p>
                    @endif
                @endforeach
                @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
                    @if(in_array($document->document_type->id,['01','03']))
                        @foreach($accounts as $account)
                            <tr>
                                <td colspan="2">
                                    {{$account->bank->description}}
                                    {{$account->currency_type->description}}
                                    N°: {{$account->number}}
                                    @if($account->cci)
                                        CCI: {{$account->cci}}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @endif
                @if ($document->retention)
                    <tr>
                        <td colspan="2">
                            <p class="font-bold">Información de la retención</p>
                            <p>Base imponible de la retención: S/ {{ $document->getRetentionTaxBase() }}</p>
                            <p>Porcentaje de la retención: {{ $document->retention->percentage * 100 }}%</p>
                            <p>Monto de la retención: S/ {{ $document->retention->amount_pen }}</p>
                        </td>
                    </tr>
                @endif
            </table>
        </td>
        <td width="18%" class="text-right">
            <img src="data:image/png;base64, {{ $document->qr }}" style="margin-right: -10px;" width="16%"/>
            <p style="font-size: 8px">{{ $document->hash }}</p>
        </td>
    </tr>
</table>
</body>
</html>
