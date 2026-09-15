<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecoKm extends Model
{
    protected $table = 'precos_km';

    public const CREATED_AT = null;

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['zona', 'preco_por_km_cve', 'atualizado_por'];

    protected function casts(): array
    {
        return [
            'preco_por_km_cve' => 'float',
            'atualizado_em' => 'datetime',
        ];
    }
}
