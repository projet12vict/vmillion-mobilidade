<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PontoPartida extends Model
{
    protected $table = 'pontos_partida';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'nome',
        'cidade',
        'lat',
        'lng',
        'zona',
        'status',
        'ativo',
        'criado_por',
        'aprovado_por',
        'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'aprovado_em' => 'datetime',
        ];
    }

    /** Só um ponto aprovado e ativo é visível a passageiros e condutores. */
    public function scopeVisiveis(Builder $query): Builder
    {
        return $query->where('status', 'aprovado')->where('ativo', true);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'criado_por');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'aprovado_por');
    }
}
