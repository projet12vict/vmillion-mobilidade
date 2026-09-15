<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarmes_sos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('utilizador_id');
            $table->enum('tipo_utilizador', ['passageiro', 'condutor']);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->enum('estado', ['pendente', 'em_curso', 'resolvido'])->default('pendente');
            $table->unsignedInteger('resolvido_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('estado', 'idx_sos_estado');

            $table->foreign('utilizador_id', 'fk_sos_utilizador')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('resolvido_por', 'fk_sos_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarmes_sos');
    }
};
