<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilizador;
use App\Services\AuditoriaService;
use App\Services\LoginThrottleService;
use App\Support\HashReferencia;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Login e logout de passageiros e condutores (secção 4.2).
 *
 * O identificador pode ser o telefone ou o email; a sessão é regenerada
 * depois de autenticar, e o bloqueio por tentativas falhadas vive na própria
 * conta (ver LoginThrottleService). O volume de pedidos é travado antes de
 * chegar aqui pelo limitador 'login' (ver AppServiceProvider e routes/api.php).
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly LoginThrottleService $throttle,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function autenticar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'identificador' => ['required', 'string'],
            'senha' => ['required', 'string'],
        ], [
            'identificador.required' => 'Indique o telefone/email e a senha.',
            'senha.required' => 'Indique o telefone/email e a senha.',
        ]);

        $identificador = trim($dados['identificador']);
        $campo = str_contains($identificador, '@') ? 'email' : 'telefone';
        $valor = $campo === 'telefone' ? Telefone::normalizar($identificador) : $identificador;

        $utilizador = Utilizador::query()->where($campo, $valor)->first();

        if (! $utilizador) {
            // Gastar o mesmo tempo que gastaria a comparar uma senha real: sem
            // isto, cronometrar a resposta distinguia um telefone registado de
            // um que não existe.
            HashReferencia::consumirTempo($dados['senha']);
            $this->registarFalha(null, "{$campo} desconhecido");

            return response()->json(['erro' => 'Credenciais inválidas.'], 401);
        }

        if ($minutos = $this->throttle->minutosBloqueadaRestantes($utilizador)) {
            return response()->json(
                ['erro' => "Demasiadas tentativas. Tente novamente em {$minutos} min."],
                429
            );
        }

        if (! Hash::check($dados['senha'], $utilizador->senha_hash)) {
            $this->throttle->registarFalha($utilizador);
            $this->registarFalha($utilizador->id, 'senha incorreta');

            return response()->json(['erro' => 'Credenciais inválidas.'], 401);
        }

        if ($utilizador->status === 'pendente') {
            return response()->json(
                ['erro' => 'A sua conta de condutor está pendente de aprovação pelo administrador.'],
                403
            );
        }

        if ($utilizador->isSuspenso()) {
            return response()->json(['erro' => 'A sua conta está suspensa. Contacte o suporte.'], 403);
        }

        $this->throttle->limpar($utilizador);

        // Passageiros e condutores entram com "manter sessão iniciada": quem
        // faz login só volta ao /login quando carrega em "Sair". O cookie de
        // sessão (2 h) expulsava o condutor a meio do turno e obrigava o
        // passageiro a reautenticar-se a cada viagem; com o cookie de
        // recordação, quando a sessão já expirou o guard reautentica-o a
        // partir da coluna remember_token em vez de o expulsar.
        Auth::guard('utilizador')->login($utilizador, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'sucesso' => true,
            'tipo' => $utilizador->tipo,
            'redirect' => $utilizador->isCondutor()
                ? route('condutor.painel')
                : route('passageiro.painel'),
        ]);
    }

    /**
     * Termina a sessão de quem estiver autenticado (utilizador ou admin) e
     * devolve à landing page.
     */
    public function terminar(Request $request): RedirectResponse
    {
        foreach (['utilizador', 'admin'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }

    /**
     * Falhas de login ficam nos logs de auditoria com admin_id nulo (a coluna
     * é uma FK para administradores) e a conta visada em entidade/entidade_id;
     * o IP é acrescentado pelo AuditoriaService. Sem este registo, uma campanha
     * de tentativas contra muitas contas não deixava rasto nenhum.
     *
     * Só as falhas são registadas. O painel mostra as últimas 15 ações no
     * dashboard, e um registo por cada login bem-sucedido de passageiro
     * empurrava dali para fora as ações de backoffice que a tabela existe para
     * mostrar.
     *
     * Os detalhes são propositadamente texto fixo: o painel de administração
     * monta a tabela de logs com innerHTML, e escrever nela o identificador tal
     * como veio do pedido seria dar a quem tenta entrar uma forma de injetar
     * HTML na página de quem investiga.
     */
    private function registarFalha(?int $utilizadorId, string $motivo): void
    {
        $this->auditoria->registar(null, 'login_falhado', 'utilizadores', $utilizadorId, $motivo);
    }
}
