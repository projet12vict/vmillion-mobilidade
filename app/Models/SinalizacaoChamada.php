<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SinalizacaoChamada extends Model
{
    protected $table = 'sinalizacao_chamada';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['chamada_id', 'remetente_id', 'tipo', 'payload'];

    protected function casts(): array
    {
        return ['criado_em' => 'datetime'];
    }

    public function chamada(): BelongsTo
    {
        return $this->belongsTo(Chamada::class, 'chamada_id');
    }
}
