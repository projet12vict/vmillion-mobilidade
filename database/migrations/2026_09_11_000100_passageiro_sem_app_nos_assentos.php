<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passageiro sem app: quem entra no carro sem ter reservado (apanhado na
 * rua). O condutor marca-lhe o lugar no mapa de "Lugares" (ver
 * VeiculoService::ocuparSemApp) e o assento fica ocupado sem reserva por
 * trás — uma reserva exige uma conta de passageiro, e esta pessoa não tem.
 *
 * O nome é opcional e serve só para o condutor saber quem é quem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->boolean('ocupado_sem_app')->default(false)->after('ativo');
            $table->string('nome_sem_app', 60)->nullable()->after('ocupado_sem_app');
        });
    }

    public function down(): void
    {
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->dropColumn(['ocupado_sem_app', 'nome_sem_app']);
        });
    }
};
