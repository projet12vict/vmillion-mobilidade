<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * destinatario_id refere utilizadores(id) para condutores/passageiros/todos,
 * ou administradores(id) quando destinatario_tipo = 'admins' — por isso não
 * tem FK direta: a tabela de origem depende do tipo e é validada em código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('destinatario_id')->nullable();
            $table->enum('destinatario_tipo', ['todos', 'admins', 'condutores', 'passageiros', 'individual'])
                ->default('individual');
            $table->unsignedInteger('remetente_id');
            $table->string('titulo');
            $table->text('mensagem');
            $table->enum('tipo', ['alerta', 'informativo', 'urgente'])->default('informativo');
            $table->boolean('lida')->default(false);
            $table->dateTime('criado_em')->useCurrent();

            $table->index(['destinatario_id', 'destinatario_tipo', 'lida'], 'idx_notificacoes_destinatario');

            $table->foreign('remetente_id', 'fk_notificacao_remetente')
                ->references('id')->on('administradores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
