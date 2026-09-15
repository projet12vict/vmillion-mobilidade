<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigPreco extends Model
{
    protected $table = 'config_precos';

    public const CREATED_AT = null;

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['chave', 'valor', 'descricao', 'atualizado_por'];

    protected function casts(): array
    {
        return [
            'valor' => 'float',
            'atualizado_em' => 'datetime',
        ];
    }
}
