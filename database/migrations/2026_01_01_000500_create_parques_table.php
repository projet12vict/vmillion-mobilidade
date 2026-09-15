<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parques', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 150);
            $table->string('morada', 200);
            $table->string('cidade', 100);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedSmallInteger('capacidade_total');
            $table->unsignedSmallInteger('vagas_ocupadas')->default(0);
            $table->unsignedInteger('criado_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->foreign('criado_por', 'fk_parque_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });

        DB::statement('ALTER TABLE parques ADD CONSTRAINT chk_parque_lat CHECK (lat BETWEEN 14.7 AND 17.2)');
        DB::statement('ALTER TABLE parques ADD CONSTRAINT chk_parque_lng CHECK (lng BETWEEN -25.4 AND -22.7)');
        DB::statement('ALTER TABLE parques ADD CONSTRAINT chk_parque_vagas CHECK (vagas_ocupadas <= capacidade_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parques');
    }
};
