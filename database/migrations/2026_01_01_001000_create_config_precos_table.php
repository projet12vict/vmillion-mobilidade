<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chave/valor das configurações gerais de preço: valor_minimo, valor_maximo
 * e taxa_operacao_rota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_precos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('chave', 50)->unique();
            $table->decimal('valor', 10, 2);
            $table->string('descricao')->nullable();
            $table->unsignedInteger('atualizado_por')->nullable();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('atualizado_por', 'fk_config_precos_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_precos');
    }
};
