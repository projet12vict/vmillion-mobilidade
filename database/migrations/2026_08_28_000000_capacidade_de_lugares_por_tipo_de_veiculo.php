<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A capacidade deixa de ser 14 para todos os veículos e passa a depender do
 * tipo (config 'vmillion.veiculos.lugares_por_tipo'): o táxi leva 3
 * passageiros, no banco de trás. O CHECK original prendia 'lugares_total' ao
 * valor 14, por isso é substituído por um que só garante a coerência entre
 * total e livres.
 *
 * Os números fixos (3 lugares, layout de 14) ficam escritos aqui e não vêm da
 * configuração: uma migration descreve a transformação que já correu, e mudar
 * a configuração amanhã não pode reescrever o passado.
 */
return new class extends Migration
{
    private const LUGARES_TAXI = 3;

    public function up(): void
    {
        DB::statement('ALTER TABLE veiculos DROP CONSTRAINT chk_veiculo_lugares');

        // Os assentos a mais dos táxis já registados desaparecem, mas só os
        // que estão livres: a FK assento -> reserva é ON DELETE CASCADE, e
        // apagar um assento ocupado apagava a reserva de quem já está a
        // viajar. Um táxi apanhado com passageiros nos lugares de trás fica
        // com a capacidade que os assentos restantes justificam, até esses
        // lugares se libertarem.
        DB::statement(
            'DELETE a FROM assentos_veiculo a
             JOIN veiculos v ON v.id = a.veiculo_id
             WHERE v.tipo = ? AND a.numero > ? AND a.ocupado = 0 AND a.reserva_id IS NULL',
            ['taxi', self::LUGARES_TAXI]
        );

        // Os que ficam passam para o layout do táxi — uma só fila atrás —,
        // senão um táxi antigo mostrava ao passageiro dois lugares na fila 0
        // (junto ao condutor), que num táxi não são vendidos.
        DB::statement(
            'UPDATE assentos_veiculo a
             JOIN veiculos v ON v.id = a.veiculo_id
             SET a.fila = 1, a.coluna = a.numero
             WHERE v.tipo = ? AND a.numero <= ? AND a.ocupado = 0 AND a.reserva_id IS NULL',
            ['taxi', self::LUGARES_TAXI]
        );

        DB::statement(
            'UPDATE veiculos v
             SET v.lugares_total = GREATEST(?, (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id)),
                 v.lugares_livres = (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id AND a.ocupado = 0)
             WHERE v.tipo = ?',
            [self::LUGARES_TAXI, 'taxi']
        );

        DB::statement(
            'ALTER TABLE veiculos ADD CONSTRAINT chk_veiculo_lugares
             CHECK (lugares_total >= 1 AND lugares_livres <= lugares_total)'
        );
    }

    /**
     * Devolve os táxis aos 14 lugares do layout clássico — incluindo os
     * assentos apagados no up(), que voltam livres — antes de repor o CHECK
     * antigo, que exige 'lugares_total = 14' em todos os veículos.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE veiculos DROP CONSTRAINT chk_veiculo_lugares');

        $layout = [];
        $numero = 1;

        for ($coluna = 1; $coluna <= 2; $coluna++) {
            $layout[] = ['numero' => $numero++, 'fila' => 0, 'coluna' => $coluna];
        }

        for ($fila = 1; $fila <= 4; $fila++) {
            for ($coluna = 1; $coluna <= 3; $coluna++) {
                $layout[] = ['numero' => $numero++, 'fila' => $fila, 'coluna' => $coluna];
            }
        }

        foreach (DB::table('veiculos')->where('tipo', 'taxi')->pluck('id') as $veiculoId) {
            $existentes = DB::table('assentos_veiculo')
                ->where('veiculo_id', $veiculoId)
                ->pluck('numero')
                ->map(fn ($n) => (int) $n)
                ->all();

            $novos = [];

            foreach ($layout as $lugar) {
                if (in_array($lugar['numero'], $existentes, true)) {
                    DB::table('assentos_veiculo')
                        ->where('veiculo_id', $veiculoId)
                        ->where('numero', $lugar['numero'])
                        ->update(['fila' => $lugar['fila'], 'coluna' => $lugar['coluna']]);

                    continue;
                }

                $novos[] = $lugar + ['veiculo_id' => $veiculoId, 'ocupado' => false];
            }

            if ($novos !== []) {
                DB::table('assentos_veiculo')->insert($novos);
            }
        }

        DB::statement(
            'UPDATE veiculos v
             SET v.lugares_total = 14,
                 v.lugares_livres = (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id AND a.ocupado = 0)
             WHERE v.tipo = ?',
            ['taxi']
        );

        DB::statement('ALTER TABLE veiculos ADD CONSTRAINT chk_veiculo_lugares CHECK (lugares_total = 14 AND lugares_livres <= 14)');
    }
};
