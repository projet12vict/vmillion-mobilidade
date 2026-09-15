<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A classificação do condutor nunca é obrigatória: o passageiro pode fechar
 * o modal do fim da viagem. Sem registo dessa decisão, a reserva continuava
 * "por avaliar" e o painel voltava a abrir o modal a cada ciclo de estado.
 *
 * A data em que foi dispensada fica na própria reserva — e não no
 * navegador — para a decisão valer em qualquer dispositivo onde o
 * passageiro entre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dateTime('avaliacao_dispensada_em')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('avaliacao_dispensada_em');
        });
    }
};
