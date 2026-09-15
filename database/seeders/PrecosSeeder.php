<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Administrador;
use App\Models\ConfigPreco;
use App\Models\PrecoKm;
use Illuminate\Database\Seeder;

/**
 * Tarifário base: preço por km de cada zona e limites gerais de preço.
 */
class PrecosSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminId = Administrador::query()->where('nivel', 'super')->value('id');

        $precosKm = [
            ['zona' => 'urbana', 'preco_por_km_cve' => 5.00],
            ['zona' => 'intermunicipal', 'preco_por_km_cve' => 10.00],
        ];

        foreach ($precosKm as $preco) {
            PrecoKm::query()->firstOrCreate(
                ['zona' => $preco['zona']],
                ['preco_por_km_cve' => $preco['preco_por_km_cve'], 'atualizado_por' => $superAdminId]
            );
        }

        $config = [
            ['chave' => 'valor_minimo', 'valor' => 100.00, 'descricao' => 'Valor mínimo da viagem (CVE)'],
            ['chave' => 'valor_maximo', 'valor' => 5000.00, 'descricao' => 'Valor máximo da viagem (CVE)'],
            ['chave' => 'taxa_operacao_rota', 'valor' => 50.00, 'descricao' => 'Taxa que o condutor paga por rota completa (ida e volta), em CVE'],
        ];

        foreach ($config as $linha) {
            ConfigPreco::query()->firstOrCreate(
                ['chave' => $linha['chave']],
                ['valor' => $linha['valor'], 'descricao' => $linha['descricao']]
            );
        }
    }
}
