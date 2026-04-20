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

    $logo_path = public_path($logo);
    $has_logo = !empty($logo) && is_file($logo_path);

    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
    
    $totalProductos = count($document->items);
    $totalFilas = 6 + $totalProductos;

    if ($document->payment_method_type_id) {
        $totalFilas++;
    }

    $allowed = $totalFilas;
    if ($allowed>26){
        $allowed_items = 70 - $totalFilas;
    }elseif($totalProductos>=20){
        $allowed_items = 0;
    }
    elseif($allowed<=26){
        $allowed_items = 70 - $totalProductos;
    }

    $quantity_items = $document->items()->count();
    $cycle_items = $allowed_items - ($quantity_items * 3);
    $total_weight = 0;

    $type = App\CoreFacturalo\Helpers\Template\TemplateHelper::getTypeSoap();
@endphp
<html>
<head>
    {{--<title>{{ $tittle }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>
@if($document->state_type->id == '11')
    <div class="company_logo_box" style="position: absolute; text-align: center; top:30%;">
        <img src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png")))}}" alt="anulado" class="" style="opacity: 0.6;">
    </div>
@elseif($has_logo)
    <div class="item_watermark" style="
        position: absolute;
        top: 25%;
        left: 10%;
        width: 80%;
        height: 300px;
        text-align: center;
    ">
        <img 
            src="data:{{ mime_content_type($logo_path) }};base64,{{ base64_encode(file_get_contents($logo_path)) }}" 
            alt="{{ $company->name }}" 
            style="width: 100%; height: auto; object-fit: contain; opacity: 0.1;"
        >
    </div>
@endif
@if($document->state_type->id == '09')
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))}}"
            alt="rechazado" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
<table class="full-width">
    <tr>
        @if($has_logo)
            <td width="20%">
                <div class="company_logo_box">
                    <img src="data:{{mime_content_type($logo_path)}};base64, {{base64_encode(file_get_contents($logo_path))}}" alt="{{$company->name}}" class="company_logo" style="max-width: 150px;">
                </div>
            </td>
        @else
            <td width="20%">
            </td>
        @endif
        <td width="50%" class="pl-3">
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
            <h5 class="text-center font-bold">NOTA DE VENTA</h5>
            <h3 class="text-center font-bold">{{ $tittle }}</h3>
        </td>
    </tr>
</table>
<table class="full-width mt-3">
    <tr>
        <td width="47%" class="border-box pl-3 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Cliente</strong>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $customer->name }}
                        </td>
                    </td>
                </tr>
                <tr>
                        <td class="font-sm" width="80px">
                            <strong>{{$customer->identity_document_type->description}}</strong>
                            <td class="font-sm" width="8px">:</td>
                            <td class="font-sm">
                                {{$customer->number}}
                            </td>
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
                        <td class="font-sm align-top" width="80px">
                            <strong>Teléfono:</strong>
                        </td>
                        <td class="font-sm align-top" width="8px">:</td>
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
                    <tr>
                        <td class="font-sm" width="90px">
                            <strong>Fecha Emisión</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                           {{$document->date_of_issue->format('d-m-Y')}} / {{ $document->time_of_issue }}
                        </td>
                    </tr>
                    <tr>
                        @if ($document->due_date)
                            <td class="font-sm" width="65px">
                                <strong>Fecha Vencimiento</strong>
                            </td>
                            <td class="font-sm" width="8px">:</td>
                            <td>{{ $document->due_date->format('d-m-Y') }}</td>
                        @endif
                    </tr>
                </tr>
                <tr>
                    @if ($document->total_canceled)
                    <td class="font-sm align-top" width="50px">
                        <strong>Estado</strong>
                        <td class="font-sm align-top" width="8px">:</td>
                        <td colspan="3">CANCELADO</td>
                    </td>

                    @else
                    <td class="font-sm align-top" width="50px">
                        <strong>Estado</strong>
                        <td class="font-sm align-top" width="8px">:</td>
                        <td colspan="3">PENDIENTE DE PAGO</td>
                    </td>
                    @endif
                    @if ($document->plate_number !== null)
                        <td>
                            <tr>
                                <td width="15%">N° Placa:</td>
                                <td width="85%">{{ $document->plate_number }}</td>
                            </tr>
                        </td>
                    @endif
                </tr>
                <tr>
                    @if ($document->observation)
                    <td class="font-sm align-top" width="50px">
                        <strong>Observación</strong>
                        <td class="font-sm align-top" width="8px">:</td>
                        <td colspan="3">{{ $document->observation }}</td>
                    </td>
                    @endif
                    @if ($document->reference_data)
                        <td class="font-sm align-top" width="50px">
                            <strong>D. Referencia</strong>
                            <td class="font-sm align-top" width="8px">:</td>
                            <td colspan="3">{{ $document->reference_data }}</td>
                        </td>
                    @endif
                </tr>
                <tr>
                    @if ($document->purchase_order)
                        <td class="font-sm align-top" width="50px">
                            <strong>Orden de compra</strong>
                            <td class="font-sm align-top" width="8px">:</td>
                            <td colspan="3">{{ $document->purchase_order }}</td>
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
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
    if (!empty($row->item->model)) {
        $showModelColumn = true;
    }
    if (!empty($row->relation_item->brand->name ?? null)) {
        $showBrandColumn = true;
    }

    if ($showModelColumn && $showBrandColumn) break;
}
@endphp
@php
    $showSerieColumn = false;
    $showLoteColumn = false;
@endphp

@foreach ($document->items as $row)
    @if(property_exists($row->item, 'lots') && !empty($row->item->lots))
        @php $showSerieColumn = true; @endphp
        @break
    @endif
@endforeach

@php
    foreach ($document->items as $row) {
        if (isset($row->item->IdLoteSelected)) {
            $showLoteColumn = true;
            break;
        }
    }
@endphp
<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr class="bg-grey">
        <th class="border-top-bottom text-center py-2 cell-solid" width="8%">COD.</th>
        <th class="border-top-bottom text-center py-2 cell-solid" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-2 cell-solid" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-center py-2 cell-solid">DESCRIPCIÓN</th>
        @if($showSerieColumn) <th class="border-top-bottom text-center py-2" class="cell-solid"> SERIE </th> @endif   
        @if($showModelColumn)
            <th class="border-top-bottom text-left py-2" class="cell-solid">MODELO</th>
        @endif
        @if($showBrandColumn)
            <th class="border-top-bottom text-center py-2" class="cell-solid">MARCA</th>
        @endif        
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2" class="cell-solid">
             LOTE 
        </th> @endif
        @if($showLoteColumn) <th class="border-top-bottom text-center py-2" class="cell-solid" width="9%"> F. VENC. </th> @endif
        <th class="border-top-bottom text-center py-2 cell-solid col-total">P.UNIT</th>
        <th class="border-top-bottom text-center py-2 cell-solid" width="8%">DTO.</th>
        <th class="border-top-bottom text-center py-2 cell-solid col-total">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @php
        $colspan_total = 2;

        if($showSerieColumn) $colspan_total += 1;
        if($showLoteColumn) $colspan_total += 2;
        if($showModelColumn) $colspan_total++;
        if($showBrandColumn) $colspan_total++;
    @endphp
    @foreach($document->items as $row)
        <tr>
            <td class="text-center align-top cell-solid-rl">{{ $row->item->internal_id }}</td>
            <td class="text-center align-top cell-solid-rl">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center align-top cell-solid-rl">{{ $row->item->unit_type_id }}</td>
            <td class="text-left cell-solid-rl">
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
            @if($showSerieColumn)
                <td class="text-center align-top cell-solid-rl">
                    @if(property_exists($row->item, 'lots'))
                        @foreach($row->item->lots as $lot)
                            @if( isset($lot->has_sale) && $lot->has_sale)
                                <span style="font-size: 9px">{{ $lot->series }}</span><br>
                            @endif
                        @endforeach
                    @endif
                </td>
            @endif
            @if($showModelColumn)
                <td class="text-center align-top cell-solid-rl">{{ $row->item->model ?? '' }}</td>
            @endif
            @if($showBrandColumn)
                <td class="text-center align-top cell-solid-rl">{{ $row->relation_item->brand->name ?? '' }}</td>
            @endif
            @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
            @php
                $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
                $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);
            @endphp

            @if($showLoteColumn)
                <td class="text-center align-top desc cell-solid-rl p-1">
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
                <td class="text-center align-top desc cell-solid-rl p-1">
                    @php
                        $cleanedDate = $date_due != ''
                            ? ltrim($date_due, '/')
                            : ($row->relation_item->date_of_due ? $row->relation_item->date_of_due->format('d-m-Y') : '');
                    @endphp
            
                    {{ $cleanedDate }}
                </td>
            @endif
            <td class="text-center align-top cell-solid-rl">{{ number_format($row->unit_price, 2) }}</td>
            <td class="text-center align-top cell-solid-rl">
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
            <td class="text-center align-top cell-solid-rl">{{ number_format($row->total, 2) }}</td>
        </tr>
    @endforeach

        @for($i = 0; $i < $cycle_items; $i++)
        <tr>
            <td class="p-1 text-center align-top desc cell-solid-rl"></td>
            <td class="p-1 text-center align-top desc cell-solid-rl"></td>
            <td class="p-1 text-center align-top desc cell-solid-rl"></td>
            <td class="p-1 text-left align-top desc text-upp cell-solid-rl"></td>
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @if($showSerieColumn)
                <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
            @if($showModelColumn)
                <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
            @if($showBrandColumn)
                <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
            @if($showLoteColumn)
                <td class="p-1 text-right align-top desc cell-solid-rl"></td>
                <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
        </tr>
        @endfor
        <tr>
            @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
                <td class="p-1 text-left align-top desc cell-solid" colspan="3"><strong> VENDEDOR:</strong> {{ $document->user->name }}</td>
            @endif
            <td class="p-1 text-left align-top desc cell-solid font-bold"></td>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                OP. GRAVADA {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_taxed, 2) }}</td>
        </tr>
        <tr>
            @php
                $is_service = $document->items->every(function($item) {
                    return isset($item->item->unit_type_id) && $item->item->unit_type_id === 'ZZ';
                });
            @endphp
            <td class="p-1 text-left align-top desc cell-solid" colspan="3" rowspan="6">
                @php
                    // Solo contar productos (no servicios) para total bultos
                    $total_packages = 0;
                    $has_product = false;
                    foreach ($document->items as $itRow) {
                        $itemType = $itRow->item->item_type_id ?? null;
                        $unitType = $itRow->item->unit_type_id ?? null;
                        // Considerar como producto si no es servicio ('02') y unidad no es 'ZZ'
                        if (!in_array($itemType, ['02']) && $unitType !== 'ZZ') {
                            $total_packages += $itRow->quantity;
                            $has_product = true;
                        }
                    }
                @endphp
                @if($total_packages > 0 && $has_product)
                    <strong> Total bultos:</strong>
                        @if(((int)$total_packages != $total_packages))
                            {{ $total_packages }}
                        @else
                            {{ number_format($total_packages, 0) }}
                        @endif
                    <br>
                @endif
            </td>
            <td class="p-1 text-center align-top desc cell-solid " rowspan="6"></td>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                OP. INAFECTAS {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                OP. EXONERADAS {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                OP. GRATUITAS {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_free, 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                TOTAL DCTOS. {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_discount, 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                IGV. {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                TOTAL A PAGAR. {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total, 2) }}</td>
        </tr>

        @if($document->total_discount > 0)
            <tr>
                <td colspan="6" class="text-right font-bold">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif

        @if($document->total_charge > 0 && $document->charges)
            <tr>
                <td colspan="6" class="text-right font-bold">CARGOS ({{$document->getTotalFactor()}}%): {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif

        @php
            $change_payment = $document->getChangePayment();
        @endphp

        @if($change_payment < 0)
            <tr>
                <td colspan="6" class="text-right font-bold">VUELTO: {{ $document->currency_type->symbol }}</td>
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

@if($document->payment_method_type_id && $payments->count() == 0)
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