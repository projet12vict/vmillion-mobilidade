<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Traduz o período escolhido num filtro do painel de admin ("hoje", "esta
 * semana", "últimos 30 dias", …) para o intervalo de datas que vai à query.
 *
 * Vive aqui porque as listas de utilizadores, veículos e pagamentos oferecem
 * as mesmas opções e têm de as ler da mesma maneira — um "esta semana" que
 * começasse ao domingo numa lista e à segunda noutra confundiria quem compara
 * duas secções do mesmo painel.
 */
final class IntervaloPeriodo
{
    /**
     * "semana", "mês" e "ano" são o calendário corrente, os "N dias" contam
     * para trás a partir de hoje e o que sobra é o intervalo escrito à mão.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function paraDatas(?string $periodo, ?string $dataInicio, ?string $dataFim): array
    {
        $agora = now();

        $intervalo = match ($periodo) {
            'hoje' => [$agora->copy()->startOfDay(), $agora->copy()->endOfDay()],
            'ontem' => [$agora->copy()->subDay()->startOfDay(), $agora->copy()->subDay()->endOfDay()],
            'semana' => [$agora->copy()->startOfWeek(), $agora->copy()->endOfWeek()],
            'mes' => [$agora->copy()->startOfMonth(), $agora->copy()->endOfMonth()],
            'ano' => [$agora->copy()->startOfYear(), $agora->copy()->endOfYear()],
            '7dias' => [$agora->copy()->subDays(6)->startOfDay(), $agora->copy()->endOfDay()],
            '30dias' => [$agora->copy()->subDays(29)->startOfDay(), $agora->copy()->endOfDay()],
            default => [
                $dataInicio ? Carbon::parse($dataInicio)->startOfDay() : null,
                $dataFim ? Carbon::parse($dataFim)->endOfDay() : null,
            ],
        };

        // As datas de registo são guardadas em hora de Cabo Verde, sem fuso;
        // os extremos vão para a query no mesmo formato para a comparação não
        // escorregar um dia.
        return array_map(fn (?Carbon $d) => $d?->format('Y-m-d H:i:s'), $intervalo);
    }
}
