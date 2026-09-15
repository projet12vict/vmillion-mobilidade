<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proprietario extends Model
{
    protected $table = 'proprietarios';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['nome', 'telefone', 'nif', 'utilizador_condutor_id'];

    protected function casts(): array
    {
        return ['criado_em' => 'datetime'];
    }

    /** Conta de condutor do próprio proprietário, quando também conduz. */
    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_condutor_id');
    }

    public function condutoresDaFrota(): HasMany
    {
        return $this->hasMany(Utilizador::class, 'proprietario_id');
    }
}
