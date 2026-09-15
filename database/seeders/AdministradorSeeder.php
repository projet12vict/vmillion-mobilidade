<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Administrador;
use Illuminate\Database\Seeder;

/**
 * Super Admin de arranque.
 *
 * A senha temporária é "VMILLION#2026" e o hash abaixo é exatamente o mesmo
 * que estava em schema.sql — a flag senha_temporaria força a troca no
 * primeiro login (ver Admin\TrocarSenhaController).
 */
class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            [
                'nome' => 'Super Admin',
                'senha_hash' => '$2y$10$sxC8LOlpg4WNpzrt8pClHO74Ro6pX3l5awjzAH/S2FFPWkFv4X/gK',
                'nivel' => 'super',
                'senha_temporaria' => true,
                'ativo' => true,
            ]
        );
    }
}
