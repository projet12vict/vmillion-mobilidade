<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinoUrbano extends Model
{
    protected $table = 'destinos_urbanos';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['nome', 'lat', 'lng', 'criado_por', 'usos'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'usos' => 'integer',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'criado_por');
    }
}
