<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço fixo por rota (ponto A -> ponto B), definido pelo Super Admin.
 * distancia_km é o que permite fracionar esse preço proporcionalmente em
 * paragens intermédias (ver PricingService::precoRotaFixaFracionado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos_rotas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ponto_origem_id');
            $table->unsignedInteger('ponto_destino_id');
            $table->decimal('preco_fixo_cve', 10, 2);
            $table->decimal('distancia_km', 8, 2)->nullable();
            $table->unsignedInteger('definido_por');
            $table->dateTime('criado_em')->useCurrent();

            $table->unique(['ponto_origem_id', 'ponto_destino_id'], 'uq_rota');

            $table->foreign('ponto_origem_id', 'fk_preco_rota_origem')
                ->references('id')->on('pontos_partida')->cascadeOnDelete();
            $table->foreign('ponto_destino_id', 'fk_preco_rota_destino')
                ->references('id')->on('pontos_partida')->cascadeOnDelete();
            $table->foreign('definido_por', 'fk_preco_rota_admin')
                ->references('id')->on('administradores');
        });

        // Fecha a FK deixada em aberto na migration de 'veiculos'.
        Schema::table('veiculos', function (Blueprint $table) {
            $table->foreign('rota_fixa_id', 'fk_veiculo_rota_fixa')
                ->references('id')->on('precos_rotas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropForeign('fk_veiculo_rota_fixa');
        });

        Schema::dropIfExists('precos_rotas');
    }
};
