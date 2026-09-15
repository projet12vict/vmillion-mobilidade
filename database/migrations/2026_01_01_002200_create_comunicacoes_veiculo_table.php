<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Autofalante": mensagens entre o condutor e os passageiros de um veículo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicacoes_veiculo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('veiculo_id');
            $table->unsignedInteger('remetente_id');
            $table->unsignedInteger('destinatario_id')->nullable();
            $table->string('mensagem', 500);
            $table->enum('tipo', ['texto', 'sistema'])->default('texto');
            $table->boolean('lida')->default(false);
            $table->dateTime('criado_em')->useCurrent();

            $table->index(['veiculo_id', 'criado_em'], 'idx_comunicacoes_veiculo');

            $table->foreign('veiculo_id', 'fk_comunicacao_veiculo')
                ->references('id')->on('veiculos')->cascadeOnDelete();
            $table->foreign('remetente_id', 'fk_comunicacao_remetente')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('destinatario_id', 'fk_comunicacao_destinatario')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacoes_veiculo');
    }
};
