<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Destinos escritos/geocodificados por passageiros em viagens urbanas.
 * Não são pontos públicos como pontos_partida: servem de autocomplete e o
 * contador 'usos' ordena as sugestões.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinos_urbanos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedInteger('criado_por');
            $table->unsignedInteger('usos')->default(1);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->unique('nome', 'uq_destino_urbano_nome');
            $table->index('nome', 'idx_destinos_urbanos_nome');

            $table->foreign('criado_por', 'fk_destino_urbano_criador')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE destinos_urbanos ADD CONSTRAINT chk_destino_urbano_lat CHECK (lat BETWEEN 14.7 AND 17.2)');
        DB::statement('ALTER TABLE destinos_urbanos ADD CONSTRAINT chk_destino_urbano_lng CHECK (lng BETWEEN -25.4 AND -22.7)');
    }

    public function down(): void
    {
        Schema::dropIfExists('destinos_urbanos');
    }
};
