<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento de um pacote por um condutor, com comprovativo carregado e
 * recibo PDF gerado na aprovação. É o registo com status 'aprovado' e
 * data_validade no futuro que abre o mapa ao condutor (secção 11.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos_condutores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condutor_id');
            $table->unsignedInteger('veiculo_id');
            $table->unsignedInteger('rota_id')->nullable();
            $table->unsignedInteger('pacote_id')->nullable();
            $table->decimal('valor_pago', 10, 2);
            $table->string('referencia', 40)->unique();
            $table->dateTime('data_pagamento')->nullable();
            $table->dateTime('data_validade')->nullable();
            $table->unsignedInteger('aprovado_por')->nullable();
            $table->dateTime('aprovado_em')->nullable();
            $table->enum('status', ['pendente', 'aprovado', 'recusado'])->default('pendente');
            $table->string('recibo_path')->nullable();
            $table->string('comprovativo_path')->nullable();
            $table->enum('comprovativo_tipo', ['imagem', 'pdf'])->nullable();
            $table->text('observacao_admin')->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->index(['condutor_id', 'status', 'data_validade'], 'idx_pagcond_status');

            $table->foreign('condutor_id', 'fk_pagcond_condutor')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('veiculo_id', 'fk_pagcond_veiculo')
                ->references('id')->on('veiculos')->cascadeOnDelete();
            $table->foreign('rota_id', 'fk_pagcond_rota')
                ->references('id')->on('precos_rotas')->nullOnDelete();
            $table->foreign('pacote_id', 'fk_pagcond_pacote')
                ->references('id')->on('pacotes_pagamento')->nullOnDelete();
            $table->foreign('aprovado_por', 'fk_pagcond_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos_condutores');
    }
};
