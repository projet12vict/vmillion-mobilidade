<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PacotePagamento;
use Illuminate\Database\Seeder;

/**
 * Pacotes 'ambos' por omissão (servem qualquer veículo, urbano ou
 * intermunicipal). O Super Admin pode criar variantes por tipo de serviço
 * com preços diferentes a partir do painel.
 */
class PacotesPagamentoSeeder extends Seeder
{
    public function run(): void
    {
        $pacotes = [
            ['nome' => 'diario', 'descricao' => 'Acesso por 1 dia', 'preco' => 100.00, 'duracao_dias' => 1],
            ['nome' => 'semanal', 'descricao' => 'Acesso por 7 dias', 'preco' => 500.00, 'duracao_dias' => 7],
            ['nome' => 'mensal', 'descricao' => 'Acesso por 30 dias', 'preco' => 1500.00, 'duracao_dias' => 30],
            ['nome' => 'anual', 'descricao' => 'Acesso por 365 dias', 'preco' => 15000.00, 'duracao_dias' => 365],
        ];

        foreach ($pacotes as $pacote) {
            PacotePagamento::query()->firstOrCreate(
                ['tipo_servico' => 'ambos', 'nome' => $pacote['nome']],
                $pacote
            );
        }
    }
}
