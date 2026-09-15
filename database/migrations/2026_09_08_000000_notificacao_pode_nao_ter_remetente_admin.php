<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Até aqui toda a notificação tinha um administrador por trás: eram sempre
 * alguém do backoffice a avisar outra pessoa. Os avisos do ponto (o veículo
 * entrou no ponto, entrou na fila, saiu do ponto) nascem de um gesto do
 * condutor, sem admin nenhum envolvido — e 'remetente_id' NOT NULL obrigava
 * a inventar um remetente, atribuindo a um administrador qualquer mensagens
 * que ele nunca escreveu.
 *
 * A chave estrangeira mantém-se para as notificações que continuam a ter
 * remetente; NULL passa a significar "gerado pelo sistema".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropForeign('fk_notificacao_remetente');
        });

        DB::statement('ALTER TABLE notificacoes MODIFY remetente_id INT UNSIGNED NULL');

        Schema::table('notificacoes', function (Blueprint $table) {
            $table->foreign('remetente_id', 'fk_notificacao_remetente')
                ->references('id')->on('administradores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Uma notificação do sistema não tem remetente que se possa repor: a
        // coluna volta a NOT NULL e essas linhas deixam de caber no esquema.
        DB::table('notificacoes')->whereNull('remetente_id')->delete();

        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropForeign('fk_notificacao_remetente');
        });

        DB::statement('ALTER TABLE notificacoes MODIFY remetente_id INT UNSIGNED NOT NULL');

        Schema::table('notificacoes', function (Blueprint $table) {
            $table->foreign('remetente_id', 'fk_notificacao_remetente')
                ->references('id')->on('administradores')->cascadeOnDelete();
        });
    }
};
