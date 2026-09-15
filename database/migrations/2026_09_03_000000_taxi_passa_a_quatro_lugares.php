<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O táxi passa de 3 para 4 lugares: aos 3 do banco de trás junta-se o lugar
 * ao lado do condutor (fila 0), que até aqui não era vendido.
 *
 * Nos táxis já registados o lugar novo entra como número 4 na fila 0, em vez
 * de se renumerar o banco de trás para o layout dos táxis novos (1 na fila 0,
 * 2 a 4 atrás): renumerar mudava o lugar impresso na reserva de quem já
 * reservou, e o carro fica na mesma com a mesma disposição.
 *
 * Os números fixos ficam escritos aqui e não vêm da configuração: uma
 * migration descreve a transformação que já correu, e mudar a configuração
 * amanhã não pode reescrever o passado.
 */
return new class extends Migration
{
    private const LUGARES_TAXI = 4;

    public function up(): void
    {
        $novos = [];

        foreach (DB::table('veiculos')->where('tipo', 'taxi')->pluck('id') as $veiculoId) {
            $numeros = DB::table('assentos_veiculo')
                ->where('veiculo_id', $veiculoId)
                ->pluck('numero')
                ->map(fn ($n) => (int) $n)
                ->all();

            if (count($numeros) >= self::LUGARES_TAXI || in_array(self::LUGARES_TAXI, $numeros, true)) {
                continue;
            }

            $novos[] = [
                'veiculo_id' => $veiculoId,
                'numero' => self::LUGARES_TAXI,
                'fila' => 0,
                'coluna' => 1,
                'ocupado' => false,
            ];
        }

        if ($novos !== []) {
            DB::table('assentos_veiculo')->insert($novos);
        }

        // O CHECK em vigor (lugares_total >= 1 AND lugares_livres <=
        // lugares_total) aguenta a subida dos dois valores, por isso não é
        // preciso mexer-lhe.
        DB::statement(
            'UPDATE veiculos v
             SET v.lugares_total = (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id),
                 v.lugares_livres = (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id AND a.ocupado = 0)
             WHERE v.tipo = ?',
            ['taxi']
        );
    }

    /**
     * Volta aos 3 lugares do banco de trás. O lugar da fila 0 só desaparece
     * se estiver livre: a FK assento -> reserva é ON DELETE CASCADE, e apagar
     * um assento ocupado apagava a reserva de quem já está a viajar.
     */
    public function down(): void
    {
        DB::statement(
            'DELETE a FROM assentos_veiculo a
             JOIN veiculos v ON v.id = a.veiculo_id
             WHERE v.tipo = ? AND a.fila = 0 AND a.ocupado = 0 AND a.reserva_id IS NULL',
            ['taxi']
        );

        DB::statement(
            'UPDATE veiculos v
             SET v.lugares_total = GREATEST(?, (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id)),
                 v.lugares_livres = (SELECT COUNT(*) FROM assentos_veiculo a WHERE a.veiculo_id = v.id AND a.ocupado = 0)
             WHERE v.tipo = ?',
            [self::LUGARES_TAXI - 1, 'taxi']
        );
    }
};
