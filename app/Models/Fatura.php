<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fatura extends Model
{
    protected $table = 'faturas';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['condutor_id', 'referencia', 'valor_cve', 'estado', 'vencimento'];

    protected function casts(): array
    {
        return [
            'valor_cve' => 'float',
            'vencimento' => 'date',
            'criado_em' => 'datetime',
        ];
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }
}
