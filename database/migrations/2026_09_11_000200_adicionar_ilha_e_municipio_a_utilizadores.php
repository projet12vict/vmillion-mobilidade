<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ilha e município de residência, pedidos no registo (ver config/municipios.php).
 *
 * Nullable porque as contas criadas antes disto não os têm; o registo novo
 * exige os dois (RegistoRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->string('ilha', 30)->nullable()->after('nif');
            $table->string('municipio', 60)->nullable()->after('ilha');
        });
    }

    public function down(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->dropColumn(['ilha', 'municipio']);
        });
    }
};
