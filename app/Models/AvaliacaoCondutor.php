<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoCondutor extends Model
{
    protected $table = 'avaliacoes_condutores';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['condutor_id', 'passageiro_id', 'reserva_id', 'avaliacao', 'comentario'];

    protected function casts(): array
    {
        return [
            'avaliacao' => 'integer',
            'criado_em' => 'datetime',
        ];
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }

    public function passageiro(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'passageiro_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }
}
