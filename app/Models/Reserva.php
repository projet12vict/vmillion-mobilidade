<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    protected $table = 'reservas';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    /** Estados em que a reserva ainda liga o passageiro ao condutor. */
    public const ESTADOS_ATIVOS = ['pendente', 'confirmado', 'a_caminho', 'a_bordo'];

    /**
     * Estados em que o condutor já aceitou a reserva mas ainda não tem o
     * passageiro dentro do carro — é a partir daqui que os dois se veem no
     * mapa e trocam contacto.
     */
    public const ESTADOS_ACEITES = ['confirmado', 'a_caminho', 'a_bordo'];

    /** Estados a partir dos quais "Ir buscar" faz sentido. */
    public const ESTADOS_ANTES_DA_RECOLHA = ['pendente', 'confirmado'];

    protected $fillable = [
        'passageiro_id',
        'veiculo_id',
        'assento_id',
        'ponto_partida_id',
        'destino_id',
        'ponto_descida_nome',
        'ponto_descida_lat',
        'ponto_descida_lng',
        'passageiro_lat',
        'passageiro_lng',
        'passageiro_localizacao_em',
        'lugares',
        'motivo',
        'tipo_viagem',
        'preco_final',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'ponto_descida_lat' => 'float',
            'ponto_descida_lng' => 'float',
            'passageiro_lat' => 'float',
            'passageiro_lng' => 'float',
            'passageiro_localizacao_em' => 'datetime',
            'avaliacao_dispensada_em' => 'datetime',
            'lugares' => 'integer',
            'preco_final' => 'float',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ATIVOS);
    }

    /**
     * Reservas com marcador próprio no mapa do condutor: o passageiro já foi
     * posto no mapa por "Ir buscar" e ainda não entrou no carro. Depois de
     * embarcar deixa de ter marcador autónomo e passa a contar dentro do
     * marcador do condutor (secção 9.4).
     */
    public function scopeNoMapa(Builder $query): Builder
    {
        return $query->where('estado', 'a_caminho');
    }

    /** Passageiros dentro do veículo neste momento. */
    public function scopeABordo(Builder $query): Builder
    {
        return $query->where('estado', 'a_bordo');
    }

    /**
     * Pedido de viagem urbana ainda por reclamar: existe reserva mas nenhum
     * condutor a recolheu (veiculo_id continua a NULL).
     */
    public function scopeUrbanasEmAberto(Builder $query): Builder
    {
        return $query->where('tipo_viagem', 'urbano')
            ->whereNull('veiculo_id')
            ->where('estado', 'pendente');
    }

    /**
     * Pedido intermunicipal ainda por reclamar: o passageiro escolheu o ponto
     * (e o destino da carreira) sem escolher um veículo, por isso o pedido
     * fica visível a todos os condutores ancorados nesse ponto. Escolher um
     * lugar concreto é o caminho oposto — nasce logo com veiculo_id e só o
     * condutor desse veículo o vê (ver Passageiro\ReservaController).
     */
    public function scopeIntermunicipaisEmAbertoNoPonto(Builder $query, int $pontoPartidaId): Builder
    {
        return $query->where('reservas.tipo_viagem', 'intermunicipal')
            ->whereNull('reservas.veiculo_id')
            ->where('reservas.estado', 'pendente')
            ->where('reservas.ponto_partida_id', $pontoPartidaId);
    }

    public function passageiro(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'passageiro_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function assento(): BelongsTo
    {
        return $this->belongsTo(AssentoVeiculo::class, 'assento_id');
    }

    public function pontoPartida(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'ponto_partida_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(PontoPartida::class, 'destino_id');
    }
}
