<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PacotePagamento extends Model
{
    protected $table = 'pacotes_pagamento';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'nome',
        'tipo_servico',
        'descricao',
        'preco',
        'duracao_dias',
        'ativo',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'float',
            'duracao_dias' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }
}
