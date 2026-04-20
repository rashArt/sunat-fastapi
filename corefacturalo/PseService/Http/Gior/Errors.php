<?php

namespace Modules\PseService\Http\Gior;

/**
 * Class Errors.
 */
final class Errors
{
    public static $messages =  [
      200 => 'Operación exitosa',
      400 => 'Errores de cliente (Bad Request)',
      401 => 'Errores de cliente (Unauthorized)',
      403 => 'Errores de servidor (Forbidden)',
      404 => 'Errores de cliente (Not Found)',
      407 => 'Errores de cliente (Proxy Authentication Required)',
      409 => 'Errores de cliente (Conflict)',
      500 => 'Errores de servidor (Internal Server Error)',
      502 => 'Errores de servidor (Bad Gateway)',
    ];

    public static function getMessage($statusCode)
    {
      return self::$messages[$statusCode] ?? null;
    }
}

