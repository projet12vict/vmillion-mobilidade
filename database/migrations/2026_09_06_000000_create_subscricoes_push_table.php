<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscrições de Web Push (RFC 8291) — um registo por dispositivo/browser.
 *
 * O endpoint é o URL que o serviço de push do fabricante (FCM, Mozilla,
 * WNS) dá ao browser; é ele que identifica o dispositivo, por isso é único.
 * As duas chaves vêm da subscrição do browser e são o que permite cifrar o
 * conteúdo de ponta a ponta: o serviço de push encaminha o pacote sem nunca
 * o conseguir ler.
 *
 * A subscrição morre com o utilizador (cascade) e também sozinha: quando o
 * serviço de push responde 404/410, WebPushService apaga a linha — é a única
 * forma de saber que a app foi desinstalada ou a permissão revogada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscricoes_push', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('utilizador_id');
            // 500 e não TEXT: o endpoint é único e um índice único precisa de
            // um tamanho fixo. Os endpoints reais dos três serviços de push
            // andam pelos 100-300 caracteres.
            $table->string('endpoint', 500)->unique('uq_subscricao_endpoint');
            $table->string('chave_p256dh', 120);
            $table->string('chave_auth', 40);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('usado_em')->nullable();

            $table->index('utilizador_id', 'idx_subscricoes_push_utilizador');

            $table->foreign('utilizador_id', 'fk_subscricao_push_utilizador')
                ->references('id')->on('utilizadores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscricoes_push');
    }
};
