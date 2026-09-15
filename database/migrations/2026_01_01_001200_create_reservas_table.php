<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * veiculo_id/assento_id ficam NULL enquanto um pedido de viagem urbana está
 * em aberto (nenhum condutor o reclamou ainda).
 *
 * Numa viagem urbana, destino_id fica igual a ponto_partida_id só para
 * satisfazer a FK — o destino real é o ponto de descida (nome/lat/lng),
 * escrito ou geocodificado pelo passageiro, não escolhido de uma lista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('passageiro_id');
            $table->unsignedInteger('veiculo_id')->nullable();
            $table->unsignedInteger('assento_id')->nullable();
            $table->unsignedInteger('ponto_partida_id');
            $table->unsignedInteger('destino_id');
            $table->string('ponto_descida_nome', 200)->nullable();
            $table->decimal('ponto_descida_lat', 9, 6)->nullable();
            $table->decimal('ponto_descida_lng', 9, 6)->nullable();
            $table->decimal('passageiro_lat', 9, 6)->nullable();
            $table->decimal('passageiro_lng', 9, 6)->nullable();
            $table->dateTime('passageiro_localizacao_em')->nullable();
            $table->unsignedSmallInteger('lugares')->default(1);
            $table->enum('motivo', ['normal', 'grupo', 'passeio'])->default('normal');
            $table->enum('tipo_viagem', ['urbano', 'intermunicipal'])->default('intermunicipal');
            $table->decimal('preco_final', 10, 2);
            $table->enum('estado', ['pendente', 'confirmado', 'recusado', 'a_bordo', 'concluido'])
                ->default('pendente');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['estado', 'veiculo_id'], 'idx_reservas_estado');

            $table->foreign('passageiro_id', 'fk_reserva_passageiro')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('veiculo_id', 'fk_reserva_veiculo')
                ->references('id')->on('veiculos')->cascadeOnDelete();
            $table->foreign('assento_id', 'fk_reserva_assento')
                ->references('id')->on('assentos_veiculo')->cascadeOnDelete();
            $table->foreign('ponto_partida_id', 'fk_reserva_ponto')
                ->references('id')->on('pontos_partida');
            $table->foreign('destino_id', 'fk_reserva_destino')
                ->references('id')->on('pontos_partida');
        });

        // Fecha a FK deixada em aberto na migration de 'assentos_veiculo'.
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->foreign('reserva_id', 'fk_assento_reserva')
                ->references('id')->on('reservas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assentos_veiculo', function (Blueprint $table) {
            $table->dropForeign('fk_assento_reserva');
        });

        Schema::dropIfExists('reservas');
    }
};
