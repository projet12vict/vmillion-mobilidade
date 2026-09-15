<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecoRota extends Model
{
    protected $table = 'precos_rotas';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'ponto_origem_id',
        'ponto_destino_id',
        'preco_fixo_cve',
        'distancia_km',
        'definido_por',
    ];

    protected function casts(): array
    {
        return [
            'preco_fixo_cve' => 'float',
            'distancia_km' => 'float',
            'criado_em' => 'datetime',
        ];
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'ponto_origem_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'ponto_destino_id');
    }
}
