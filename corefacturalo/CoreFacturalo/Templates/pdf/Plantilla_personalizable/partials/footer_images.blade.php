@php
    $path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $quantity_images = $images->count();
@endphp

<html>
<head>
    <link href="{{ $path_style }}" rel="stylesheet" />
</head>
<body>
    <div class="footer-image-container">
        @if($quantity_images > 1)
            <div class="footer-images-wrapper text-center" style="--image-count: {{ $quantity_images }};">
                @foreach ($images as $image)
                    <img class="footer-image-multiple" src="{{ $image['url'] }}">
                @endforeach
            </div>
        @else
            <div class="text-center">
                <img class="footer-image-single" src="{{ $images[0]['url'] }}">
            </div>            
        @endif
    </div>
</body>
</html>