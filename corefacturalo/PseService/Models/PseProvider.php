<?php

namespace Modules\PseService\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PseProvider extends ModelTenant
{

    protected $fillable = ['name', 'description', 'active'];

    protected static function newFactory()
    {
        return \Modules\PseService\Database\factories\PseProviderFactory::new();
    }
}
