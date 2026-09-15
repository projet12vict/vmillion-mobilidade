<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O condutor passa a poder tirar e repor lugares ao veículo (botão "Lugares"
 * do painel, ver VeiculoService::retirarLugar). Um lugar tirado fica
 * desativado em vez de apagado: a FK reservas.assento_id é ON DELETE CASCADE,
 * e apagar o assento levava consigo as viagens já feitas nesse lugar.
 *
 * Todos os assentos que já existem continuam ativos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('ocupado');
        });
    }

    public function down(): void
    {
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
    }
};
