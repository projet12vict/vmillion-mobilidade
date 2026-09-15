<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssentoVeiculo extends Model
{
    protected $table = 'assentos_veiculo';

    public $timestamps = false;

    protected $fillable = [
        'veiculo_id', 'numero', 'fila', 'coluna', 'ocupado', 'ativo',
        'ocupado_sem_app', 'nome_sem_app', 'reserva_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fila' => 'integer',
            'coluna' => 'integer',
            'ocupado' => 'boolean',
            'ativo' => 'boolean',
            'ocupado_sem_app' => 'boolean',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }
}
