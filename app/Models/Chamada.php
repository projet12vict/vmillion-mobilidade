<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chamada extends Model
{
    protected $table = 'chamadas';

    public const CREATED_AT = 'iniciada_em';

    public const UPDATED_AT = 'atualizada_em';

    protected $fillable = ['remetente_id', 'destinatario_id', 'estado', 'atendida_em', 'terminada_em'];

    protected function casts(): array
    {
        return [
            'iniciada_em' => 'datetime',
            'atendida_em' => 'datetime',
            'terminada_em' => 'datetime',
            'atualizada_em' => 'datetime',
        ];
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'remetente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'destinatario_id');
    }

    public function sinais(): HasMany
    {
        return $this->hasMany(SinalizacaoChamada::class, 'chamada_id');
    }

    public function temParticipante(int $utilizadorId): bool
    {
        return $this->remetente_id === $utilizadorId || $this->destinatario_id === $utilizadorId;
    }
}
