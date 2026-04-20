<?php

namespace App\CoreFacturalo\Helpers\QrCode;

use Mpdf\QrCode\QrCode;
use Mpdf\QrCode\Output;

class QrCodeGenerate
{
    private $heightQr;
    public function displayPNGBase64($value, $w = 150, $level = 'L', $background = [255, 255, 255], $color = [0, 0, 0], $filename = null, $quality = 0)
    {
        $qrCode = new QrCode($value, $level);
        $output = new Output\Png();
        $data = $output->output($qrCode, $w, $background, $color, $quality);
        $base64 = base64_encode($data);
        $heightQr = getimagesizefromstring($data);

        $this->setHeightQr($heightQr[1]);// El segundo indice es la altura de la imagen en string
        return $base64; 
    }

    public function setHeightQr(string $height)
    {
        $this->heightQr = $height;
    }

    public function getHeightQr()
    {
        return $this->heightQr;
    }
}