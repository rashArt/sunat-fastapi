@php
    $path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
@endphp
<head>
    <link href="{{ $path_style }}" rel="stylesheet" />
</head>
<body>
<table class="full-width">
    <tr>
        <td class="text-center desc font-bold">
            Para consultar el comprobante ingresar a {!! url('/buscar') !!}
            <br>
            Representación impresa de la <span style="text-transform: capitalize" class="text-capitalize">{{ $document->document_type->description }}</span>
        </td>
    </tr>
</table>
</body>