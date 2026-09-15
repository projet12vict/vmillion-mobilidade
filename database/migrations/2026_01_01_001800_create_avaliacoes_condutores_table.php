<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uma avaliação por reserva (uq_avaliacao_reserva) — é isso que impede o
 * mesmo passageiro de avaliar a mesma viagem duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes_condutores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condutor_id');
            $table->unsignedInteger('passageiro_id');
            $table->unsignedInteger('reserva_id');
            $table->unsignedTinyInteger('avaliacao');
            $table->string('comentario', 500)->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->unique('reserva_id', 'uq_avaliacao_reserva');

            $table->foreign('condutor_id', 'fk_avaliacao_condutor')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('passageiro_id', 'fk_avaliacao_passageiro')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('reserva_id', 'fk_avaliacao_reserva')
                ->references('id')->on('reservas')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE avaliacoes_condutores ADD CONSTRAINT chk_avaliacao_valor CHECK (avaliacao BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes_condutores');
    }
};
