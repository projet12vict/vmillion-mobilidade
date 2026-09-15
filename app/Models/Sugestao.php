<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sugestao extends Model
{
    protected $table = 'sugestoes';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['utilizador_id', 'tipo', 'titulo', 'descricao', 'condutor_id', 'status'];

    protected function casts(): array
    {
        return [
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
        ];
    }

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }
}
