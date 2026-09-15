<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos_km', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('zona', ['urbana', 'intermunicipal'])->unique();
            $table->decimal('preco_por_km_cve', 10, 2);
            $table->unsignedInteger('atualizado_por');
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('atualizado_por', 'fk_preco_km_admin')
                ->references('id')->on('administradores');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precos_km');
    }
};
