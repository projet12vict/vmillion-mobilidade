<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas de backoffice, população separada de 'utilizadores' (guard 'admin').
 * Níveis: gestor < admin < super. Só o super cria outros administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administradores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 150);
            $table->string('email', 150)->unique();
            $table->string('senha_hash');
            $table->enum('nivel', ['gestor', 'admin', 'super'])->default('admin');
            $table->boolean('senha_temporaria')->default(true);
            $table->boolean('ativo')->default(true);
            $table->unsignedTinyInteger('tentativas_login')->default(0);
            $table->dateTime('bloqueado_ate')->nullable();
            $table->unsignedInteger('criado_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->foreign('criado_por', 'fk_admin_criado_por')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administradores');
    }
};
