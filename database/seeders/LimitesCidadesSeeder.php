<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LimiteCidade;
use Illuminate\Database\Seeder;

/**
 * Centro e raio de cada cidade servida. Sem estes limites o motor de preços
 * cai no recurso de "metade da rota" para separar urbano de intermunicipal.
 */
class LimitesCidadesSeeder extends Seeder
{
    public function run(): void
    {
        $limites = [
            ['cidade' => 'Praia', 'lat' => 14.9177, 'lng' => -23.5092, 'raio_km' => 6.0],
            ['cidade' => 'Santa Catarina', 'lat' => 15.0983, 'lng' => -23.6703, 'raio_km' => 4.0],
            ['cidade' => 'Tarrafal', 'lat' => 15.2785, 'lng' => -23.7519, 'raio_km' => 3.5],
            ['cidade' => 'Ribeira Grande de Santiago', 'lat' => 14.9153, 'lng' => -23.6047, 'raio_km' => 3.0],
        ];

        foreach ($limites as $limite) {
            LimiteCidade::query()->firstOrCreate(['cidade' => $limite['cidade']], $limite);
        }
    }
}
