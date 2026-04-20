@php
    $customer = $document->customer;
    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
@endphp
<html>
<body class="ticket">
    @php
        $count = (int)$document->itemChunk->quantity;
    @endphp

    <br>
    <table class="full-width" style="border: 1px solid #000;">
        <tr>
            <td class="desc">{{ $company->name }}</td>
            <td class="text-right desc">Fecha: {{ $document->created_at->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="text-right desc">{{ $document_number }}</td>
        </tr>
        <tr>
            <td colspan="2" class="text-center">
                <h4>{{ $document->itemChunk->item->description }} {{ $document->currency_type->symbol }} {{ number_format($document->itemChunk->unit_price, 2)}}</h4>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="text-center desc">
                VÁLIDO SOLO POR HOY
            </td>
        </tr>
    </table>
</body>
</html>
