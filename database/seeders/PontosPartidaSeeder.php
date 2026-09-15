<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PontoPartida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Pontos iniciais da ilha de Santiago, já aprovados (visíveis desde o
 * primeiro arranque).
 */
class PontosPartidaSeeder extends Seeder
{
    public function run(): void
    {
        $pontos = [
            ['nome' => 'Mercado de Sucupira', 'cidade' => 'Praia', 'lat' => 14.917500, 'lng' => -23.509400, 'zona' => 'urbana'],
            ['nome' => 'Estádio da Várzea', 'cidade' => 'Praia', 'lat' => 14.915800, 'lng' => -23.515000, 'zona' => 'urbana'],
            ['nome' => 'Assomada', 'cidade' => 'Santa Catarina', 'lat' => 15.098300, 'lng' => -23.670300, 'zona' => 'intermunicipal'],
            ['nome' => 'Tarrafal', 'cidade' => 'Tarrafal', 'lat' => 15.278500, 'lng' => -23.751900, 'zona' => 'intermunicipal'],
            ['nome' => 'Cidade Velha', 'cidade' => 'Ribeira Grande de Santiago', 'lat' => 14.915300, 'lng' => -23.604700, 'zona' => 'intermunicipal'],
        ];

        foreach ($pontos as $ponto) {
            PontoPartida::query()->firstOrCreate(
                ['nome' => $ponto['nome']],
                $ponto + [
                    'status' => 'aprovado',
                    'ativo' => true,
                    'aprovado_em' => Carbon::now(),
                ]
            );
        }
    }
}
