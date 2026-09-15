<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessões em base de dados (SESSION_DRIVER=database).
 *
 * O sistema original usava ficheiros de sessão do PHP e tinha de fazer
 * session_write_close() manualmente em cada guarda de API, porque o ficheiro
 * ficava trancado até ao fim do pedido e os vários polls paralelos de cada
 * painel (posição, notificações, chat, chamada) bloqueavam-se uns aos outros.
 * Com o driver de base de dados esse lock exclusivo por pedido deixa de
 * existir e o problema desaparece por construção.
 *
 * Não há tabela 'users' nem 'password_reset_tokens': as contas vivem em
 * 'utilizadores' e 'administradores' (ver config/auth.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
