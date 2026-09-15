<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pontos públicos de partida/chegada. Só um ponto com status 'aprovado' é
 * visível a passageiros e condutores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pontos_partida', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 150)->unique();
            $table->string('cidade', 100);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->enum('zona', ['urbana', 'intermunicipal'])->default('urbana');
            $table->enum('status', ['pendente', 'aprovado', 'recusado'])->default('pendente');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('criado_por')->nullable();
            $table->unsignedInteger('aprovado_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('aprovado_em')->nullable();

            $table->foreign('criado_por', 'fk_ponto_admin')
                ->references('id')->on('administradores')->nullOnDelete();
            $table->foreign('aprovado_por', 'fk_ponto_aprovado_por')
                ->references('id')->on('administradores')->nullOnDelete();
        });

        DB::statement('ALTER TABLE pontos_partida ADD CONSTRAINT chk_ponto_lat CHECK (lat BETWEEN 14.7 AND 17.2)');
        DB::statement('ALTER TABLE pontos_partida ADD CONSTRAINT chk_ponto_lng CHECK (lng BETWEEN -25.4 AND -22.7)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pontos_partida');
    }
};
