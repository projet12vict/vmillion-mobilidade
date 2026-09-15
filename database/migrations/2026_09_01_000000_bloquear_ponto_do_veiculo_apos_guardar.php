<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardar o ponto de partida/destino de um veículo passa a trancá-lo durante
 * 24 horas (config 'vmillion.veiculos.bloqueio_ponto_horas'): sem isso, um
 * condutor podia saltar de ponto em ponto a cada pedido novo, entrando à
 * frente de quem já lá estava à espera.
 *
 * A data até quando está trancado fica na própria linha do veículo — e não
 * na sessão ou num contador em memória — porque é ela que o administrador
 * limpa quando autoriza a alteração antes do prazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dateTime('ponto_bloqueado_ate')->nullable()->after('ultima_posicao_em');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn('ponto_bloqueado_ate');
        });
    }
};
