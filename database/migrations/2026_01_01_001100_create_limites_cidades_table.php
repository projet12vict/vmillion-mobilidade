<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centro + raio de cada cidade. É isto que permite separar o troço urbano do
 * troço intermunicipal de uma viagem entre cidades (segmentação real em vez
 * de metade da rota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limites_cidades', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cidade', 100)->unique();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->decimal('raio_km', 6, 2);
            $table->unsignedInteger('atualizado_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('atualizado_por', 'fk_limite_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });

        DB::statement('ALTER TABLE limites_cidades ADD CONSTRAINT chk_limite_lat CHECK (lat BETWEEN 14.7 AND 17.2)');
        DB::statement('ALTER TABLE limites_cidades ADD CONSTRAINT chk_limite_lng CHECK (lng BETWEEN -25.4 AND -22.7)');
        DB::statement('ALTER TABLE limites_cidades ADD CONSTRAINT chk_limite_raio CHECK (raio_km > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('limites_cidades');
    }
};
