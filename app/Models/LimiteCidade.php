<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimiteCidade extends Model
{
    protected $table = 'limites_cidades';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['cidade', 'lat', 'lng', 'raio_km', 'atualizado_por'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'raio_km' => 'float',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }
}
