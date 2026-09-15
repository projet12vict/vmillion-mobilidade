<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna 'remember_token' em utilizadores, para o condutor ficar autenticado
 * até carregar em "Sair".
 *
 * O painel do condutor é uma ferramenta de trabalho que fica aberta o dia
 * todo (e é reaberta no telemóvel a cada turno): o cookie de sessão de 2 h
 * mandava-o para o /login a meio do serviço. Com esta coluna o guard de
 * sessão pode emitir o cookie de "manter sessão iniciada" e reautenticar a
 * sessão quando a antiga já expirou (ver LoginController::autenticar).
 *
 * A coluna existe para os dois tipos porque é o guard 'utilizador' que a usa;
 * só é preenchida para quem entra com 'remember'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->string('remember_token', 100)->nullable()->after('senha_hash');
        });
    }

    public function down(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
