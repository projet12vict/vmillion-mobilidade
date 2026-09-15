<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chamada de voz (WebRTC) entre condutor e passageiro com viagem em comum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamadas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('remetente_id');
            $table->unsignedInteger('destinatario_id');
            $table->enum('estado', ['iniciada', 'atendida', 'recusada', 'terminada'])->default('iniciada');
            $table->dateTime('iniciada_em')->useCurrent();
            $table->dateTime('atendida_em')->nullable();
            $table->dateTime('terminada_em')->nullable();
            $table->dateTime('atualizada_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['destinatario_id', 'remetente_id', 'estado'], 'idx_chamadas_participantes');

            $table->foreign('remetente_id', 'fk_chamada_remetente')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('destinatario_id', 'fk_chamada_destinatario')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamadas');
    }
};
