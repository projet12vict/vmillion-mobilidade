<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_auditoria', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('admin_id')->nullable();
            $table->string('acao', 100);
            $table->string('entidade', 60)->nullable();
            $table->unsignedInteger('entidade_id')->nullable();
            $table->text('detalhes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('criado_em')->useCurrent();

            $table->index('criado_em', 'idx_logs_criado_em');

            $table->foreign('admin_id', 'fk_log_admin')
                ->references('id')->on('administradores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};
