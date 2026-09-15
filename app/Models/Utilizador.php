<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Passageiros e condutores — a tabela não se chama 'users' e a senha não se
 * chama 'password', daí os overrides de getAuthPassword/getAuthPasswordName.
 *
 * A coluna remember_token existe (migração 2026_09_02_000000) e o nome por
 * omissão serve: é ela que sustenta o "manter sessão iniciada" de passageiros
 * e condutores, cujos painéis não podem cair no /login enquanto a conta não
 * carregar em "Sair".
 */
class Utilizador extends Authenticatable
{
    protected $table = 'utilizadores';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'tipo',
        'nome',
        'telefone',
        'nif',
        'ilha',
        'municipio',
        'email',
        'senha_hash',
        'status',
        'proprietario_id',
        'tentativas_login',
        'bloqueado_ate',
    ];

    protected $hidden = ['senha_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'bloqueado_ate' => 'datetime',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
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

    public function isCondutor(): bool
    {
        return $this->tipo === 'condutor';
    }

    public function isPassageiro(): bool
    {
        return $this->tipo === 'passageiro';
    }

    public function isSuspenso(): bool
    {
        return $this->status === 'suspenso';
    }

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(Proprietario::class, 'proprietario_id');
    }

    /** Frota de que este condutor é dono (papel duplo, secção 8). */
    public function proprietarioAssociado(): HasOne
    {
        return $this->hasOne(Proprietario::class, 'utilizador_condutor_id');
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class, 'condutor_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'passageiro_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoCondutor::class, 'condutor_id');
    }

    public function avaliacoesRecebidas(): HasMany
    {
        return $this->hasMany(AvaliacaoCondutor::class, 'condutor_id');
    }
}
