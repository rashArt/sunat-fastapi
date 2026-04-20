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
                <h5 class="text-center">OPORTUNIDAD DE VENTA</h5>
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
            <h5 class="text-center">OPORTUNIDAD DE VENTA</h5>
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
                @if ($document->person->contact)
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Contacto</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->person->contact->full_name }} - {{ $document->person->contact->phone }}
                    </td>
                </tr>
                @endif
                @if ($document->payment_method_type)
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>T. Pago</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->payment_method_type->description }}
                    </td>
                </tr>
                @endif
                @if ($document->quotation)
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Cotización</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->quotation->number_full }}
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
                        {{ $document->date_of_issue->format('Y-m-d') }}
                    </td>
                </tr>
                @if($document->date_of_due)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Fecha de vencimiento</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->date_of_due->format('Y-m-d') }}
                    </td>
                </tr>
                @endif
                @if($document->purchase_order)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Orden de compra</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->purchase_order->number_full }}
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>             
    {{-- <tr>
        <td class="align-top">Vendedor:</td>
        <td colspan="3">
            {{ $document->user->name }}
        </td>
    </tr>     --}}
</table>

<table class="full-width mt-3">
    @if ($document->observation)
        <tr class="border-box">
            <td width="15%" class="align-top">Observación: </td>
            <td width="85%">{{ $document->observation }}</td>
        </tr>
    @endif
</table>

<table class="full-width mt-1">
    @if ($document->detail)
        <tr class="border-box">
            <td width="15%" class="align-top">Detalle: </td>
            <td width="85%">{{ $document->detail }}</td>
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
    $quantity_items = $document->items()->count();
    $allowed_items = 40;
    $cycle_items = $allowed_items - ($quantity_items * 1);
@endphp

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr class="bg-grey">
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">CANT.</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid" width="8%">UNIDAD</th>
        <th class="border-top-bottom text-center py-1 desc cell-solid">DESCRIPCIÓN</th>
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
            <td class="text-left desc cell-solid-rl p-1">
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
            <td class="text-right align-top desc cell-solid-rl p-1">{{ number_format($row->unit_price, 2) }}</td>
            <td class="text-right align-top desc cell-solid-rl p-1">
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
            <td class="text-right align-top desc cell-solid-rl p-1">{{ number_format($row->total, 2) }}</td>
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
    </tr>
    @endfor
    <tr>
        <td class="p-1 text-left align-top desc cell-solid" colspan="3"><strong>VENDEDOR</strong> {{ $document->user->name }} </td>
        <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="2">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
        <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_taxed, 2) }}</td>
    </tr>
        @if($document->total_exportation > 0)
            <tr>
                <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="5">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif        
        @if($document->total_discount > 0)
            <tr>
                <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">DESCUENTO TOTAL: {{ $document->currency_type->symbol }}</td>
                <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ number_format($document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td colspan="5" class="p-1 text-right align-top desc cell-solid font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
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
    <br>
    <br>


</table>

    @inject('file_service', 'Modules\Sale\Services\SaleOpportunityFileService')

    @foreach($document->files as $file)
        @php
            $is_image = $file_service->isImage($file->filename);
            $image = null;

            if ($is_image) {
                $image = $file_service->getFile($file->filename);
            }
        @endphp

        @if(!empty($image) && str_contains($image, 'base64,'))
            <div width="100%" class="mt-5">
                <img src="{{ $image }}" />
            </div>
        @endif
    @endforeach

</body>
</html>
