<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacotes de acesso (diário/semanal/mensal/anual) que o condutor paga para
 * operar. O preço é definido pelo Super Admin e pode variar por tipo de
 * serviço, daí a chave única (tipo_servico, nome).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacotes_pagamento', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 20);
            $table->enum('tipo_servico', ['urbano', 'intermunicipal', 'ambos'])->default('ambos');
            $table->string('descricao')->nullable();
            $table->decimal('preco', 10, 2);
            $table->unsignedInteger('duracao_dias');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('criado_por')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['tipo_servico', 'nome'], 'uq_pacote_tipo_nome');

            $table->foreign('criado_por', 'fk_pacote_criado_por')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacotes_pagamento');
    }
};
