<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistoController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\Chamada\ChamadaController;
use App\Http\Controllers\Chamada\SinalizacaoController;
use App\Http\Controllers\Comunicacao\ComunicacaoController;
use App\Http\Controllers\Condutor;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\Passageiro;
use App\Http\Controllers\Push\SubscricaoPushController;
use App\Http\Controllers\RotaController;
use App\Http\Controllers\SosController;
use App\Http\Controllers\SugestaoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de API
|--------------------------------------------------------------------------
|
| Carregadas com o prefixo /api e dentro do grupo de middleware 'web' (ver
| bootstrap/app.php): usam a mesma sessão e o mesmo token CSRF das páginas,
| porque é assim que os painéis as chamam (fetch com credentials same-origin).
|
| Os caminhos são exatamente os dos antigos ficheiros public/api/*.php sem a
| extensão — /api/passageiro/veiculos_disponiveis.php passou a
| /api/passageiro/veiculos_disponiveis. Manter os nomes permitiu converter os
| painéis (milhares de linhas de JS inline) apagando apenas o sufixo, em vez
| de reescrever à mão cada uma das dezenas de chamadas.
|
| Os endpoints de administração que agrupavam várias operações num campo
| 'acao' continuam a fazê-lo, com o controller a despachar para o método
| correspondente — o contrato com o painel não mudou.
|
*/

// ---------------------------------------------------------------------
// Autenticação (público)
// ---------------------------------------------------------------------
// O limitador 'login' (ver AppServiceProvider) conta por IP e por
// identificador: o bloqueio por conta do LoginThrottleService, sozinho, não
// travava password spraying contra muitas contas ao mesmo tempo.
Route::post('auth/login', [LoginController::class, 'autenticar'])
    ->middleware('throttle:login')
    ->name('auth.login');
Route::post('auth/registar', [RegistoController::class, 'registar'])
    ->middleware('throttle:registo')
    ->name('auth.registar');
Route::post('auth/login_admin', [AdminLoginController::class, 'autenticar'])
    ->middleware('throttle:login')
    ->name('auth.login_admin');

// ---------------------------------------------------------------------
// Passageiros e condutores
// ---------------------------------------------------------------------
Route::middleware('auth:utilizador')->group(function () {
    Route::get('rota', RotaController::class)->name('rota');
    Route::post('sos', SosController::class)->name('sos');
    Route::post('sugestoes/enviar', [SugestaoController::class, 'enviar'])->name('sugestoes.enviar');

    Route::get('avaliacao/consultar', [AvaliacaoController::class, 'consultar'])->name('avaliacao.consultar');
    Route::post('avaliacao/criar', [AvaliacaoController::class, 'criar'])
        ->middleware('tipo:passageiro')
        ->name('avaliacao.criar');
    Route::post('avaliacao/dispensar', [AvaliacaoController::class, 'dispensar'])
        ->middleware('tipo:passageiro')
        ->name('avaliacao.dispensar');

    Route::get('notificacoes/listar', [NotificacaoController::class, 'listar'])->name('notificacoes.listar');
    Route::post('notificacoes/marcar_lida', [NotificacaoController::class, 'marcarLida'])->name('notificacoes.marcar_lida');

    // Autofalante: aberto aos dois lados, com as permissões de canal
    // resolvidas dentro do controller (só quem tem viagem em comum).
    Route::prefix('comunicacao')->name('comunicacao.')->group(function () {
        Route::get('listar', [ComunicacaoController::class, 'listar'])->name('listar');
        Route::post('enviar', [ComunicacaoController::class, 'enviar'])->name('enviar');
        Route::post('marcar_lida', [ComunicacaoController::class, 'marcarLida'])->name('marcar_lida');
    });

    // Notificações push: o registo do dispositivo é do utilizador
    // autenticado, seja passageiro ou condutor — os dois recebem chamadas.
    Route::prefix('push')->name('push.')->group(function () {
        Route::get('chave', [SubscricaoPushController::class, 'chave'])->name('chave');
        Route::post('subscrever', [SubscricaoPushController::class, 'subscrever'])->name('subscrever');
        Route::post('cancelar', [SubscricaoPushController::class, 'cancelar'])->name('cancelar');
    });

    Route::prefix('chamada')->name('chamada.')->group(function () {
        Route::post('iniciar', [ChamadaController::class, 'iniciar'])->name('iniciar');
        Route::post('responder', [ChamadaController::class, 'responder'])->name('responder');
        Route::post('desligar', [ChamadaController::class, 'desligar'])->name('desligar');
        Route::get('verificar', [ChamadaController::class, 'verificar'])->name('verificar');
        Route::post('pulso', [ChamadaController::class, 'pulso'])->name('pulso');
        Route::post('sinalizar', [SinalizacaoController::class, 'guardar'])->name('sinalizar');
        Route::get('sinais', [SinalizacaoController::class, 'novos'])->name('sinais');
    });
});

// ---------------------------------------------------------------------
// Passageiro
// ---------------------------------------------------------------------
Route::middleware(['auth:utilizador', 'tipo:passageiro'])
    ->prefix('passageiro')
    ->name('passageiro.')
    ->group(function () {
        Route::get('destinos_urbanos', [Passageiro\PontoController::class, 'destinosUrbanos'])->name('destinos_urbanos');

        Route::get('veiculos_disponiveis', [Passageiro\VeiculoController::class, 'disponiveis'])->name('veiculos_disponiveis');
        Route::get('assentos', [Passageiro\VeiculoController::class, 'assentos'])->name('assentos');

        Route::post('reservar', [Passageiro\ReservaController::class, 'reservar'])->name('reservar');
        Route::post('pedir_urbano', [Passageiro\ReservaController::class, 'pedirUrbano'])->name('pedir_urbano');
        Route::post('pedir_ponto', [Passageiro\ReservaController::class, 'pedirPonto'])->name('pedir_ponto');
        Route::get('minha_reserva', [Passageiro\ReservaController::class, 'minhaReserva'])->name('minha_reserva');
        Route::post('cancelar_reserva', [Passageiro\ReservaController::class, 'cancelar'])->name('cancelar_reserva');
        Route::post('finalizar_viagem', [Passageiro\ReservaController::class, 'finalizar'])->name('finalizar_viagem');
        Route::post('atualizar_destino', [Passageiro\ReservaController::class, 'atualizarDestino'])->name('atualizar_destino');

        Route::post('localizacao', [Passageiro\LocalizacaoController::class, 'atualizar'])->name('localizacao');
        Route::get('meus_condutores', [Passageiro\CondutorController::class, 'meusCondutores'])->name('meus_condutores');
    });

// O perfil é editável por passageiros e por condutores, por isso fica fora do
// grupo acima (o antigo passageiro/perfil.php também não exigia o tipo).
//
// Os pontos de partida aprovados são dados públicos do sistema (nome, cidade,
// coordenadas) e ficam pela mesma razão fora do grupo 'tipo:passageiro': o
// painel do condutor desenha-os no mapa e enche com eles os selectores de
// partida/destino (condutor/painel.blade.php: carregarPontosDropdowns). A
// restrição por tipo devolvia 401 ao condutor, o que além de deixar o mapa sem
// pontos o atirava para o login (kgApiJSON redireciona em 401).
Route::middleware('auth:utilizador')->group(function () {
    Route::get('passageiro/pontos', [Passageiro\PontoController::class, 'listar'])->name('passageiro.pontos');
    Route::get('passageiro/perfil', [Passageiro\PerfilController::class, 'mostrar'])->name('passageiro.perfil.mostrar');
    Route::post('passageiro/perfil', [Passageiro\PerfilController::class, 'atualizar'])->name('passageiro.perfil.atualizar');
});

// ---------------------------------------------------------------------
// Condutor
// ---------------------------------------------------------------------
Route::middleware(['auth:utilizador', 'tipo:condutor'])
    ->prefix('condutor')
    ->name('condutor.')
    ->group(function () {
        Route::get('veiculos', [Condutor\VeiculoController::class, 'listar'])->name('veiculos.listar');
        Route::post('veiculos', [Condutor\VeiculoController::class, 'registar'])->name('veiculos.registar');
        Route::post('veiculo_ponto', [Condutor\VeiculoController::class, 'definirPonto'])->name('veiculo_ponto');
        Route::post('veiculo_eliminar', [Condutor\VeiculoController::class, 'eliminar'])->name('veiculo_eliminar');
        Route::get('assentos', [Condutor\VeiculoController::class, 'assentos'])->name('assentos');
        Route::post('veiculo_lugares', [Condutor\VeiculoController::class, 'alterarLugares'])->name('veiculo_lugares');

        Route::post('posicao', [Condutor\PosicaoController::class, 'atualizar'])->name('posicao');

        Route::post('fila_entrar', [Condutor\FilaController::class, 'entrar'])->name('fila_entrar');
        Route::post('fila_sair', [Condutor\FilaController::class, 'sair'])->name('fila_sair');

        Route::get('rotas_fixas', Condutor\RotaFixaController::class)->name('rotas_fixas');
        Route::get('outros_condutores', Condutor\OutrosCondutoresController::class)->name('outros_condutores');

        Route::get('passageiros', [Condutor\PassageiroController::class, 'doVeiculo'])->name('passageiros');
        Route::get('passageiros_urbanos', [Condutor\PassageiroController::class, 'urbanos'])->name('passageiros_urbanos');
        Route::get('passageiros_ponto', [Condutor\PassageiroController::class, 'doPonto'])->name('passageiros_ponto');
        // O mesmo "Ir buscar" serve os dois tipos de pedido em aberto (urbano
        // e do ponto); o nome antigo fica porque já está em uso no painel.
        Route::post('recolher_urbano', [Condutor\PassageiroController::class, 'recolher'])->name('recolher_urbano');
        Route::post('recolher_pedido', [Condutor\PassageiroController::class, 'recolher'])->name('recolher_pedido');
        Route::post('reserva_estado', [Condutor\PassageiroController::class, 'mudarEstado'])->name('reserva_estado');

        Route::get('pagamentos', [Condutor\PagamentoController::class, 'listar'])->name('pagamentos.listar');
        Route::post('pagamentos', [Condutor\PagamentoController::class, 'solicitar'])->name('pagamentos.solicitar');
        Route::get('comprovativo', [Condutor\PagamentoController::class, 'comprovativo'])->name('comprovativo');
        Route::get('recibo', [Condutor\PagamentoController::class, 'recibo'])->name('recibo');
    });

// ---------------------------------------------------------------------
// Administração
// ---------------------------------------------------------------------
// 'senha_definitiva' aplica-se ao grupo todo e sai só na troca de senha: uma
// conta ainda com a senha temporária que o Super Admin lhe entregou não faz
// mais nada no backoffice. Está no grupo, e não rota a rota, para uma rota
// nova nascer protegida em vez de nascer aberta.
Route::middleware(['auth:admin', 'senha_definitiva'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
        Route::get('logs', Admin\LogController::class)->name('logs');
        Route::post('trocar_senha', Admin\TrocarSenhaController::class)
            ->withoutMiddleware('senha_definitiva')
            ->name('trocar_senha');

        Route::get('utilizadores', [Admin\UtilizadorController::class, 'listar'])->name('utilizadores.listar');
        Route::post('utilizadores', [Admin\UtilizadorController::class, 'agir'])->name('utilizadores.agir');

        Route::get('veiculos', [Admin\VeiculoController::class, 'listar'])->name('veiculos.listar');
        Route::post('veiculos', [Admin\VeiculoController::class, 'agir'])->name('veiculos.agir');

        Route::get('pontos', [Admin\PontoController::class, 'listar'])->name('pontos.listar');
        Route::post('pontos', [Admin\PontoController::class, 'agir'])->name('pontos.agir');

        // Editor de mapa: veículos, passageiros e parques de todo o sistema.
        Route::get('mapa', Admin\MapaController::class)->name('mapa');

        Route::get('parques', [Admin\ParqueController::class, 'listar'])->name('parques.listar');
        Route::post('parques', [Admin\ParqueController::class, 'agir'])->name('parques.agir');

        Route::get('proprietarios', [Admin\ProprietarioController::class, 'listar'])->name('proprietarios.listar');
        Route::post('proprietarios', [Admin\ProprietarioController::class, 'agir'])->name('proprietarios.agir');

        Route::get('pagamentos', [Admin\PagamentoController::class, 'listar'])->name('pagamentos.listar');
        Route::post('pagamentos', [Admin\PagamentoController::class, 'agir'])->name('pagamentos.agir');
        Route::get('recibo', [Admin\PagamentoController::class, 'recibo'])->name('recibo');
        Route::get('comprovativo', [Admin\PagamentoController::class, 'comprovativo'])->name('comprovativo');

        Route::get('sugestoes', [Admin\SugestaoController::class, 'listar'])->name('sugestoes.listar');
        Route::post('sugestoes', [Admin\SugestaoController::class, 'agir'])->name('sugestoes.agir');

        Route::get('sos', [Admin\SosController::class, 'listar'])->name('sos.listar');
        Route::post('sos', [Admin\SosController::class, 'atualizar'])->name('sos.atualizar');

        Route::get('notificacoes', [Admin\NotificacaoController::class, 'listar'])->name('notificacoes.listar');
        Route::post('notificacoes', [Admin\NotificacaoController::class, 'agir'])->name('notificacoes.agir');

        // Grupos inteiramente reservados ao Super Admin. Onde uma única ação
        // é que é restrita (reverter um pagamento, suspender um condutor,
        // eliminar um ponto em definitivo, enviar notificações), a
        // verificação vive dentro do controller, porque o endpoint também
        // serve ações abertas a qualquer administrador.
        Route::middleware('nivel:super')->group(function () {
            Route::get('precos', [Admin\PrecoController::class, 'listar'])->name('precos.listar');
            Route::post('precos', [Admin\PrecoController::class, 'agir'])->name('precos.agir');

            Route::get('administradores', [Admin\AdministradorController::class, 'listar'])->name('administradores.listar');
            Route::post('administradores', [Admin\AdministradorController::class, 'agir'])->name('administradores.agir');

            Route::get('limites_cidades', [Admin\LimiteCidadeController::class, 'listar'])->name('limites_cidades.listar');
            Route::post('limites_cidades', [Admin\LimiteCidadeController::class, 'agir'])->name('limites_cidades.agir');
        });
    });
