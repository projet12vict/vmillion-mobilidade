<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A FK de rota_fixa_id -> precos_rotas só é adicionada na migration dessa
 * tabela, que é criada depois desta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condutor_id');
            $table->string('matricula', 20)->unique();
            $table->enum('tipo', ['hiace', 'taxi', 'autocarro']);
            $table->enum('tipo_servico', ['urbano', 'intermunicipal', 'ambos'])->default('ambos');
            $table->string('cor', 40);
            $table->string('modelo', 80);
            $table->unsignedTinyInteger('lugares_total')->default(14);
            $table->unsignedTinyInteger('lugares_livres')->default(14);
            $table->enum('estado', ['no_ponto', 'na_fila', 'em_movimento', 'partiu_da_fila', 'chegou_destino'])
                ->default('no_ponto');
            $table->boolean('aprovado')->default(false);
            $table->unsignedInteger('ponto_partida_id')->nullable();
            $table->unsignedInteger('destino_id')->nullable();
            $table->unsignedInteger('rota_fixa_id')->nullable();
            $table->unsignedSmallInteger('posicao_fila')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->dateTime('ultima_posicao_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->index(['estado', 'aprovado', 'ponto_partida_id'], 'idx_veiculos_estado');

            $table->foreign('condutor_id', 'fk_veiculo_condutor')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
            $table->foreign('ponto_partida_id', 'fk_veiculo_ponto')
                ->references('id')->on('pontos_partida')->nullOnDelete();
            $table->foreign('destino_id', 'fk_veiculo_destino')
                ->references('id')->on('pontos_partida')->nullOnDelete();
        });

        DB::statement('ALTER TABLE veiculos ADD CONSTRAINT chk_veiculo_lugares CHECK (lugares_total = 14 AND lugares_livres <= 14)');
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
