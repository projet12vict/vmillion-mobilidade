<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagamentoCondutor extends Model
{
    protected $table = 'pagamentos_condutores';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'condutor_id',
        'veiculo_id',
        'rota_id',
        'pacote_id',
        'valor_pago',
        'referencia',
        'data_pagamento',
        'data_validade',
        'aprovado_por',
        'aprovado_em',
        'status',
        'recibo_path',
        'comprovativo_path',
        'comprovativo_tipo',
        'observacao_admin',
    ];

    protected function casts(): array
    {
        return [
            'valor_pago' => 'float',
            'data_pagamento' => 'datetime',
            'data_validade' => 'datetime',
            'aprovado_em' => 'datetime',
            'criado_em' => 'datetime',
        ];
    }

    /** Pagamento aprovado e ainda dentro da validade. */
    public function scopeValidos(Builder $query): Builder
    {
        return $query->where('status', 'aprovado')->where('data_validade', '>=', now());
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacotePagamento::class, 'pacote_id');
    }

    public function rota(): BelongsTo
    {
        return $this->belongsTo(PrecoRota::class, 'rota_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'aprovado_por');
    }
}
