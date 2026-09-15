<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmeSos extends Model
{
    protected $table = 'alarmes_sos';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['utilizador_id', 'tipo_utilizador', 'lat', 'lng', 'estado', 'resolvido_por'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

    public function resolvedor(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'resolvido_por');
    }
}
