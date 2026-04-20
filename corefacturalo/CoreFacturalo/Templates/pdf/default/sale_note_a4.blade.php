@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');

    $left =  ($document->series) ? $document->series : $document->prefix;
    $tittle = $left.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $payments = $document->payments;
    // $accounts = \App\Models\Tenant\BankAccount::all();
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

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
                    <h6>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h6>
                    <h6>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h6>
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">NOTA DE VENTA</h5>
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
                    <h6>{{ ($establishment->email !== '-')? $establishment->email : '' }}</h6>
                    <h6>{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</h6>
                </div>
            </td>
            <td width="30%" class="border-box py-4 px-2 text-center">
                <h5 class="text-center">NOTA DE VENTA</h5>
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
        <td>{{$document->date_of_issue->format('Y-m-d')}} / {{ $document->time_of_issue }}</td>
    </tr>
    <tr>
        <td>{{ $customer->identity_document_type->description }}:</td>
        <td>{{ $customer->number }}</td>

        @if ($document->due_date)
            <td class="align-top">Fecha Vencimiento:</td>
            <td>{{ $document->getFormatDueDate() }}</td>
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
    </tr>
    <tr>
        <td>Teléfono:</td>
        <td>{{ $customer->telephone }}</td>
        @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
            <td>Vendedor:</td>
            <td> @if($document->seller_id != 0){{$document->seller->name }} @else {{ $document->user->name }} @endif</td>
        @endif
    </tr>
    @if ($document->consigned_id)
    @php
        $consigned =App\Models\Tenant\Consigned::where('id',$document->consigned_id)->first();
        $district = App\Models\Tenant\Catalogs\District::where('id', $document->consigned_ubigeo)->first();
        $consigned_phone = App\Models\Tenant\PersonAddress::where('person_id', $document->customer_id)
                ->where('address', $document->consigned_address)
                ->where('consigned_id', $document->consigned_id)
                ->first();
        if($district){
            $department = $district->province->department;
            $province = $district->province;
        }
    @endphp
    <tr>
        <td class="align-top">Consignado:</td>
        <td colspan="3">
            {{ $consigned->number }} -
            {{ $consigned->name }} -
            {{ ($consigned_phone->phone)? $consigned_phone->phone : ' ' }} -
            {{ $document->consigned_address }}
            {{($district) ? ' , '.$district->description.' , '.$province->description.' , '.$department->description: ' '}}
        </td>
    </tr>
@endif
    @if ($document->plate_number !== null)
    <tr>
        <td >N° Placa:</td>
        <td >{{ $document->plate_number }}</td>
    </tr>
    @endif
    @if ($document->total_canceled)
    <tr>
        <td class="align-top">Estado:</td>
        <td colspan="3">CANCELADO</td>
    </tr>
    @else
    <tr>
        <td class="align-top">Estado:</td>
        <td colspan="3">PENDIENTE DE PAGO</td>
    </tr>
    @endif
    @if ($document->observation)
    <tr>
        <td class="align-top">Observación:</td>
        <td colspan="3">{{ $document->observation }}</td>
    </tr>
    @endif
    @if ($document->reference_data)
        <tr>
            <td class="align-top">D. Referencia:</td>
            <td colspan="3">{{ $document->reference_data }}</td>
        </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td class="align-top">Orden de compra:</td>
            <td colspan="3">{{ $document->purchase_order }}</td>
        </tr>
    @endif
</table>

@if ($document->isPointSystem())
    <table class="full-width mt-3">
        <tr>
            <td width="15%">P. ACUMULADOS</td>
            <td width="8px">:</td>
            <td>{{ $document->person->accumulated_points }}</td>

            <td width="140px">PUNTOS POR LA COMPRA</td>
            <td width="8px">:</td>
            <td>{{ $document->getPointsBySale() }}</td>
        </tr>
    </table>
@endif


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
    $show_series_column = false;
    foreach($document->items as $row) {
        if (isset($row->item->lots)) {
            foreach($row->item->lots as $lot) {
                if(isset($lot->has_sale) && $lot->has_sale) {
                    $show_series_column = true;
                    break 2;
                }
            }
        }
    }
@endphp
@php
$showModelColumn = false;
$showBrandColumn = false;

foreach ($document->items as $row) {
    if (!empty($row->item->model)) {
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
        <th class="border-top-bottom text-center py-2" width="8%">COD.</th>
        <th class="border-top-bottom text-center py-2" width="7%">CANT.</th>
        <th class="border-top-bottom text-center py-2" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-left py-2">DESCRIPCIÓN</th>
        @if($show_series_column) <th class="border-top-bottom text-center py-2 px-1"> SERIE </th> @endif
        @if($showModelColumn)
            <th class="border-top-bottom text-left py-2 px-1">MODELO</th>
        @endif
        @if($showBrandColumn)
            <th class="border-top-bottom text-center py-2 px-1">MARCA</th>
        @endif
        @php
            $showLoteColumn = false;

            foreach ($document->items as $row) {
                if (isset($row->item->IdLoteSelected)) {
                    $showLoteColumn = true;
                    break;
                }
            }
        @endphp
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2 px-1">
             LOTE
        </th> @endif
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2" width="9%"> F. VENC. </th> @endif
        <th class="border-top-bottom text-right py-2 col-total">P.UNIT</th>
        <th class="border-top-bottom text-right py-2" width="8%">DTO.</th>
        <th class="border-top-bottom text-right py-2 col-total">TOTAL</th>
    </tr>
    </thead>
    <tbody>
        @php
            $colspan_total = 6;

            if($show_series_column) $colspan_total++;
            if($showLoteColumn) $colspan_total += 2;
            if($showModelColumn) $colspan_total++;
            if($showBrandColumn) $colspan_total++;
        @endphp
        @foreach($document->items as $row)
        @inject('items', 'App\Models\Tenant\Item')
        @php
            $internal_id = isset($row->item->internal_id) ? $row->item->internal_id : $items->find($row->item_id)->internal_id;
        @endphp
        <tr>
            <td class="text-center align-top">{{ $internal_id }}</td>
            <td class="text-center align-top">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center align-top">{{ $row->item->unit_type_id }}</td>
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
                        @if(isset($dtos->factor))
                            <br/><span style="font-size: 9px">{{ $dtos->factor * 100 }}% {{$dtos->description }}</span>
                        @endif
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
                    <span style="font-size: 9px">*** Canjeado por {{$row->item->used_points_for_exchange}}  puntos ***</span>
                @endif

            </td>
            @if($show_series_column) <td class="text-center align-top">
                @isset($row->item->lots)
                    @foreach($row->item->lots as $lot)
                        @if( isset($lot->has_sale) && $lot->has_sale)
                            <span style="font-size: 9px">
                                {{ $lot->series }}
                                @if(!$loop->last) - @endif
                            </span>
                        @endif
                    @endforeach
                @endisset
            </td> @endif
            @if($showModelColumn)
                <td class="text-left align-top">{{ $row->item->model ?? '' }}</td>
            @endif

            @if($showBrandColumn)
                <td class="text-left align-top">{{ $row->relation_item->brand->name ?? '' }}</td>
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
            <td class="text-right align-top">{{ number_format($row->unit_price, 2) }}</td>
            <td class="text-right align-top">
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
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        {{-- @if($document->total_taxed > 0)
             <tr>
                <td colspan="6" class="text-right font-bold">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif --}}
        @if($document->total_discount > 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif
        {{--<tr>
            <td colspan="6" class="text-right font-bold">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>--}}

        @if($document->total_charge > 0 && $document->charges)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">CARGOS ({{$document->getTotalFactor()}}%): {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif

        <tr>
            <td colspan="{{ $colspan_total }}" class="text-right font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold">{{ number_format($document->total, 2) }}</td>
        </tr>

        @php
            $change_payment = $document->getChangePayment();
        @endphp

        @if($change_payment < 0)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-right font-bold">VUELTO: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format(abs($change_payment),2, ".", "") }}</td>
            </tr>
        @endif

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
    @php
    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
    @endphp
    {{-- Condicion de pago  Crédito / Contado --}}
    <table class="full-width">
        <tr>
            <td>
                <strong>CONDICIÓN DE PAGO: {{ $paymentCondition }} </strong>
            </td>
        </tr>
    </table>

    @if($document->payment_method_type_id)
    <table class="full-width">
        <tr>
            <td>
                <strong>MÉTODO DE PAGO: </strong>{{ $document->payment_method_type->description }}
            </td>
        </tr>
    </table>
    @endif

    @if ($document->payment_condition_id === '01')
    @if($payments->count())
    <table class="full-width">
        <tr>
            <td><strong>PAGOS:</strong></td>
        </tr>
        @php $payment = 0; @endphp
        @foreach($payments as $row)
        <tr>
            <td>&#8226; {{ $row->payment_method_type->description }}
                - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment + $row->change }}</td>
        </tr>
        @endforeach
        </tr>
    </table>
    @endif
    @else
    <table class="full-width">
        @foreach($document->fee as $key => $quote)
        <tr>
            <td>
                &#8226; {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }}
                / Fecha: {{ $quote->date->format('d-m-Y') }} /
                Monto: {{ $quote->currency_type->symbol }}{{ $quote->amount }}</td>
        </tr>
        @endforeach
        </tr>
    </table>
    @endif
    @if ($document->custom_fields_data && count((array)$document->custom_fields_data) > 0)
    <br>
    <table class="full-width">
        @foreach ($document->custom_fields_data as $field_slug => $field_value)
            <tr>
                <td>
                    @php
                        $custom_field = \Modules\CustomField\Models\CustomField::where('slug', $field_slug)->first();
                        $field_name = ($custom_field) ? $custom_field->name : str_replace('_', ' ', ucfirst($field_slug));
                    @endphp
                    {{ $field_name }}
                </td>
                <td width="8px">:</td>
                <td>
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

{{-- @if($document->payment_method_type_id && $payments->count() == 0)
    <table class="full-width">
        <tr>
            <td>
                <strong>PAGO: </strong>{{ $document->payment_method_type->description }}
            </td>
        </tr>
    </table>
@endif

@if($payments->count())

<table class="full-width">
<tr>
    <td>
    <strong>PAGOS:</strong> </td></tr>
        @php
            $payment = 0;
        @endphp
        @foreach($payments as $row)
            <tr><td>- {{ $row->date_of_payment->format('d/m/Y') }} - {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment + $row->change }}</td></tr>
            @php
                $payment += (float) $row->payment;
            @endphp
        @endforeach
        <tr><td><strong>SALDO:</strong> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td>
    </tr>

</table>
@endif --}}
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
