<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal de sinalização WebRTC (offer/answer/ICE) de cada chamada, lido por
 * polling incremental a partir do último id conhecido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinalizacao_chamada', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chamada_id');
            $table->unsignedInteger('remetente_id');
            $table->enum('tipo', ['offer', 'answer', 'ice']);
            $table->text('payload');
            $table->dateTime('criado_em')->useCurrent();

            $table->index(['chamada_id', 'id'], 'idx_sinal_chamada');

            $table->foreign('chamada_id', 'fk_sinal_chamada')
                ->references('id')->on('chamadas')->cascadeOnDelete();
            $table->foreign('remetente_id', 'fk_sinal_remetente')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinalizacao_chamada');
    }
};
