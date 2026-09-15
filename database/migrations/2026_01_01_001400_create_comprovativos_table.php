<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprovativos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condutor_id');
            $table->string('ficheiro_path');
            $table->decimal('valor_cve', 10, 2)->nullable();
            $table->enum('estado', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->unsignedInteger('revisto_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('revisto_em')->nullable();

            $table->foreign('condutor_id', 'fk_comprovativo_condutor')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('revisto_por', 'fk_comprovativo_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprovativos');
    }
};
