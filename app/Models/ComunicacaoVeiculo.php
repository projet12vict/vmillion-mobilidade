<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComunicacaoVeiculo extends Model
{
    protected $table = 'comunicacoes_veiculo';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['veiculo_id', 'remetente_id', 'destinatario_id', 'mensagem', 'tipo', 'lida'];

    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'remetente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'destinatario_id');
    }
}
