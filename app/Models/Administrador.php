<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Contas de backoffice (guard 'admin'). Níveis: gestor < admin < super.
 */
class Administrador extends Authenticatable
{
    protected $table = 'administradores';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'nome',
        'email',
        'senha_hash',
        'nivel',
        'senha_temporaria',
        'ativo',
        'tentativas_login',
        'bloqueado_ate',
        'criado_por',
    ];

    protected $hidden = ['senha_hash'];

    protected function casts(): array
    {
        return [
            'senha_temporaria' => 'boolean',
            'ativo' => 'boolean',
            'bloqueado_ate' => 'datetime',
            'criado_em' => 'datetime',
            'tentativas_login' => 'integer',
            'senha_hash' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->senha_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function isSuper(): bool
    {
        return $this->nivel === 'super';
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(self::class, 'criado_por');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogAuditoria::class, 'admin_id');
    }
}
