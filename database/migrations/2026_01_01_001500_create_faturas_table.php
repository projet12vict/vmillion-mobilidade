<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condutor_id');
            $table->string('referencia', 50)->unique();
            $table->decimal('valor_cve', 10, 2);
            $table->enum('estado', ['pendente', 'paga', 'vencida'])->default('pendente');
            $table->date('vencimento');
            $table->dateTime('criado_em')->useCurrent();

            $table->foreign('condutor_id', 'fk_fatura_condutor')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
