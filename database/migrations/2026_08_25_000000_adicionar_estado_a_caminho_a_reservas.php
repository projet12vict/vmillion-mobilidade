<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado intermédio do ciclo de recolha (secção 9.4): entre aceitar a reserva
 * e o passageiro entrar mesmo no carro, o condutor carrega em "Ir buscar" e a
 * reserva fica 'a_caminho' — é esse estado, e não o estado do cliente, que põe
 * o passageiro no mapa e que sobrevive a um refresh da página.
 *
 * O valor é acrescentado no FIM da lista de propósito: o MySQL guarda o ENUM
 * pelo índice interno, e só um valor novo no fim é que é uma alteração
 * instantânea, sem reescrever as linhas já existentes. Nada no código ordena
 * por reservas.estado, portanto a posição na lista não tem outro efeito.
 */
return new class extends Migration
{
    private const ESTADOS_NOVOS = "'pendente','confirmado','recusado','a_bordo','concluido','a_caminho'";

    private const ESTADOS_ANTIGOS = "'pendente','confirmado','recusado','a_bordo','concluido'";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE reservas MODIFY estado ENUM('.self::ESTADOS_NOVOS.") NOT NULL DEFAULT 'pendente'"
        );
    }

    public function down(): void
    {
        // Uma reserva 'a_caminho' é uma reserva já aceite pelo condutor, ainda
        // sem ninguém a bordo: 'confirmado' é o estado equivalente no esquema
        // antigo. Sem esta conversão o ALTER esvaziaria a coluna dessas linhas.
        DB::table('reservas')->where('estado', 'a_caminho')->update(['estado' => 'confirmado']);

        DB::statement(
            'ALTER TABLE reservas MODIFY estado ENUM('.self::ESTADOS_ANTIGOS.") NOT NULL DEFAULT 'pendente'"
        );
    }
};
