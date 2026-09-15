<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 14 lugares fixos por veículo: fila 0 (junto ao condutor) com 2 bancos,
 * filas 1 a 4 com 3 bancos cada. A FK de reserva_id -> reservas é adicionada
 * na migration de 'reservas'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assentos_veiculo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('veiculo_id');
            $table->unsignedTinyInteger('numero');
            $table->unsignedTinyInteger('fila');
            $table->unsignedTinyInteger('coluna');
            $table->boolean('ocupado')->default(false);
            $table->unsignedInteger('reserva_id')->nullable();

            $table->unique(['veiculo_id', 'numero'], 'uq_assento_veiculo');

            $table->foreign('veiculo_id', 'fk_assento_veiculo')
                ->references('id')->on('veiculos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assentos_veiculo');
    }
};
