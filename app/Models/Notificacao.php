<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * destinatario_id aponta para utilizadores ou administradores consoante
 * destinatario_tipo — não há relação Eloquent única possível, por isso o
 * destinatário é resolvido em código onde for preciso.
 *
 * remetente_id a null é uma notificação do sistema (os avisos do ponto, em
 * App\Services\AvisoViagemService): não há administrador nenhum por trás
 * dela.
 */
class Notificacao extends Model
{
    protected $table = 'notificacoes';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'destinatario_id',
        'destinatario_tipo',
        'remetente_id',
        'titulo',
        'mensagem',
        'tipo',
        'lida',
    ];

    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'remetente_id');
    }
}
