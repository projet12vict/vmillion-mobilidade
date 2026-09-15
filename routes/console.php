<?php

use App\Support\CifraWebPush;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Gera o par de chaves VAPID que o Web Push exige (ver config/vmillion.php →
 * push). Corre-se uma vez por instalação: a chave pública fica presa às
 * subscrições que os browsers já fizeram, por isso trocá-la obriga a esvaziar
 * a tabela subscricoes_push.
 */
Artisan::command('push:chaves', function () {
    $chaves = CifraWebPush::gerarChavesVapid();

    $this->info('Par de chaves VAPID gerado. Copie estas linhas para o .env:');
    $this->newLine();
    $this->line('VMILLION_PUSH_CHAVE_PUBLICA='.$chaves['publica']);
    $this->line('VMILLION_PUSH_CHAVE_PRIVADA='.$chaves['privada']);
    $this->newLine();
    $this->warn('A chave privada é um segredo: nunca a comite nem a exponha ao browser.');
})->purpose('Gerar o par de chaves VAPID para as notificações push');
