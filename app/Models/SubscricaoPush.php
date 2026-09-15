<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um dispositivo (browser) que aceitou receber notificações push.
 *
 * O mesmo utilizador pode ter várias: telemóvel, tablet e o browser do
 * computador são subscrições diferentes, cada uma com o seu par de chaves.
 */
class SubscricaoPush extends Model
{
    protected $table = 'subscricoes_push';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['utilizador_id', 'endpoint', 'chave_p256dh', 'chave_auth', 'usado_em'];

    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            'usado_em' => 'datetime',
        ];
    }

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }
}
