<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugestoes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('utilizador_id');
            $table->enum('tipo', ['sugestao', 'reclamacao']);
            $table->string('titulo');
            $table->text('descricao');
            $table->unsignedInteger('condutor_id')->nullable();
            $table->enum('status', ['pendente', 'visto', 'implementado', 'resolvido'])->default('pendente');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['tipo', 'status'], 'idx_sugestoes_tipo_status');

            $table->foreign('utilizador_id', 'fk_sugestao_utilizador')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('condutor_id', 'fk_sugestao_condutor')
                ->references('id')->on('utilizadores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestoes');
    }
};
