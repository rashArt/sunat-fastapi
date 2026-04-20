<?php

namespace App\CoreFacturalo\Requests\Api\Transform;

use App\CoreFacturalo\Requests\Api\Transform\Common\DocumentVoidedTransform;
use Carbon\Carbon;

class VoidedTransform
{
    public static function transform($inputs)
    {
        
        $date_format = Carbon::createFromFormat('d-m-Y', $inputs['fecha_de_emision_de_documentos'])->format('Y-m-d');
        return [
            'date_of_reference' => $date_format,
            'documents' => DocumentVoidedTransform::transform($inputs),
        ];
    }
}