<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    protected $table = 'veiculos';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'condutor_id',
        'matricula',
        'tipo',
        'tipo_servico',
        'cor',
        'modelo',
        'lugares_total',
        'lugares_livres',
        'estado',
        'aprovado',
        'ponto_partida_id',
        'destino_id',
        'rota_fixa_id',
        'posicao_fila',
        'lat',
        'lng',
        'ultima_posicao_em',
        'ponto_bloqueado_ate',
    ];

    protected function casts(): array
    {
        return [
            'aprovado' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'lugares_total' => 'integer',
            'lugares_livres' => 'integer',
            'posicao_fila' => 'integer',
            'ultima_posicao_em' => 'datetime',
            'ponto_bloqueado_ate' => 'datetime',
            'criado_em' => 'datetime',
        ];
    }

    /**
     * O ponto de partida/destino ficou trancado ao ser guardado e o prazo
     * ainda não passou (secção 9.2). O administrador liberta antes disso
     * pondo 'ponto_bloqueado_ate' a null.
     */
    public function pontoBloqueado(): bool
    {
        return $this->ponto_bloqueado_ate !== null && $this->ponto_bloqueado_ate->isFuture();
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }

    public function pontoPartida(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'ponto_partida_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'destino_id');
    }

    public function rotaFixa(): BelongsTo
    {
        return $this->belongsTo(PrecoRota::class, 'rota_fixa_id');
    }

    public function assentos(): HasMany
    {
        return $this->hasMany(AssentoVeiculo::class, 'veiculo_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'veiculo_id');
    }
}
