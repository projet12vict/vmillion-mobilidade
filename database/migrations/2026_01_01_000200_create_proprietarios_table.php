<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Donos de frota. Podem não conduzir; quando conduzem, utilizador_condutor_id
 * aponta para a sua própria conta de condutor (papel duplo, secção 8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proprietarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 150);
            $table->string('telefone', 20)->unique();
            $table->char('nif', 9)->unique();
            $table->unsignedInteger('utilizador_condutor_id')->nullable();
            $table->dateTime('criado_em')->useCurrent();
        });

        DB::statement("ALTER TABLE proprietarios ADD CONSTRAINT chk_prop_telefone CHECK (telefone REGEXP '^\\\\+238[0-9]{7}$')");
        DB::statement("ALTER TABLE proprietarios ADD CONSTRAINT chk_prop_nif CHECK (nif REGEXP '^[0-9]{9}$')");

        // Fecha o ciclo utilizadores <-> proprietarios.
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->foreign('proprietario_id', 'fk_utilizador_proprietario')
                ->references('id')->on('proprietarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->dropForeign('fk_utilizador_proprietario');
        });

        Schema::dropIfExists('proprietarios');
    }
};
