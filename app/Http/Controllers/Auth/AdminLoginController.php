<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Services\AuditoriaService;
use App\Services\LoginThrottleService;
use App\Support\HashReferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Login de administradores (secção 4.3), separado do dos utilizadores.
 * O Super Admin nunca é bloqueado por tentativas falhadas.
 */
class AdminLoginController extends Controller
{
    public function __construct(
        private readonly LoginThrottleService $throttle,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function autenticar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'string'],
            'senha' => ['required', 'string'],
        ], [
            'email.required' => 'Indique o email e a senha.',
            'senha.required' => 'Indique o email e a senha.',
        ]);

        $admin = Administrador::query()->where('email', trim($dados['email']))->first();

        if (! $admin || ! $admin->ativo) {
            // Igualar o tempo de resposta ao do caminho com conta ativa, para
            // não se poder descobrir por cronometragem que emails de
            // backoffice existem (ver HashReferencia).
            HashReferencia::consumirTempo($dados['senha']);
            $this->registarFalha($admin?->id, $admin ? 'conta inativa' : 'email desconhecido');

            return response()->json(['erro' => 'Credenciais inválidas.'], 401);
        }

        if (! $admin->isSuper() && $minutos = $this->throttle->minutosBloqueadaRestantes($admin)) {
            return response()->json(
                ['erro' => "Demasiadas tentativas. Tente novamente em {$minutos} min."],
                429
            );
        }

        if (! Hash::check($dados['senha'], $admin->senha_hash)) {
            $this->throttle->registarFalha($admin);
            $this->registarFalha($admin->id, 'senha incorreta');

            return response()->json(['erro' => 'Credenciais inválidas.'], 401);
        }

        $this->throttle->limpar($admin);

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        $this->auditoria->registar($admin->id, 'login', 'administradores', $admin->id);

        return response()->json([
            'sucesso' => true,
            'senha_temporaria' => $admin->senha_temporaria,
            'redirect' => $admin->senha_temporaria
                ? route('admin.trocar-senha')
                : route('admin.painel'),
        ]);
    }

    /**
     * Tentativa falhada de entrar no backoffice. Fica com admin_id nulo — a
     * tentativa não é uma ação de um administrador autenticado — e a conta
     * visada em entidade/entidade_id. Os detalhes são texto fixo, nunca o
     * email escrito no formulário: o painel monta a tabela de logs com
     * innerHTML.
     */
    private function registarFalha(?int $adminId, string $motivo): void
    {
        $this->auditoria->registar(null, 'login_admin_falhado', 'administradores', $adminId, $motivo);
    }
}
