<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passageiros e condutores. A coluna proprietario_id existe já aqui mas a sua
 * chave estrangeira só é adicionada na migration de 'proprietarios' — as duas
 * tabelas referenciam-se mutuamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilizadores', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('tipo', ['passageiro', 'condutor']);
            $table->string('nome', 150);
            $table->string('telefone', 20)->unique();
            $table->char('nif', 9)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('senha_hash');
            $table->enum('status', ['ativo', 'pendente', 'suspenso'])->default('ativo');
            $table->unsignedInteger('proprietario_id')->nullable();
            $table->unsignedTinyInteger('tentativas_login')->default(0);
            $table->dateTime('bloqueado_ate')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['tipo', 'status'], 'idx_utilizadores_tipo_status');
        });

        DB::statement("ALTER TABLE utilizadores ADD CONSTRAINT chk_telefone CHECK (telefone REGEXP '^\\\\+238[0-9]{7}$')");
        DB::statement("ALTER TABLE utilizadores ADD CONSTRAINT chk_nif CHECK (nif REGEXP '^[0-9]{9}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('utilizadores');
    }
};
