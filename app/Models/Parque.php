<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parque extends Model
{
    protected $table = 'parques';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'nome',
        'morada',
        'cidade',
        'lat',
        'lng',
        'capacidade_total',
        'vagas_ocupadas',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'capacidade_total' => 'integer',
            'vagas_ocupadas' => 'integer',
            'criado_em' => 'datetime',
        ];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'criado_por');
    }
}
