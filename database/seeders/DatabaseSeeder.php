<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dados iniciais de arranque do sistema: Super Admin, pontos de Santiago,
 * tarifários base, limites de cidade e pacotes de pagamento.
 *
 * Corresponde ao bloco SEED de legacy/database_sql/schema.sql.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdministradorSeeder::class,
            PontosPartidaSeeder::class,
            PrecosSeeder::class,
            LimitesCidadesSeeder::class,
            PacotesPagamentoSeeder::class,
        ]);
    }
}
