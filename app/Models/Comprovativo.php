<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comprovativo extends Model
{
    protected $table = 'comprovativos';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['condutor_id', 'ficheiro_path', 'valor_cve', 'estado', 'revisto_por', 'revisto_em'];

    protected function casts(): array
    {
        return [
            'valor_cve' => 'float',
            'criado_em' => 'datetime',
            'revisto_em' => 'datetime',
        ];
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'condutor_id');
    }
}
