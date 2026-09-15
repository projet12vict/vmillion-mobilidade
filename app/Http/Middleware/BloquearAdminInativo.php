<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Administrador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reconfirma o estado da conta de backoffice a cada pedido — o equivalente de
 * BloquearUtilizadorSuspenso para o guard 'admin'.
 *
 * O 'ativo' só era lido no login (ver AdminLoginController), por isso
 * desativar um administrador no painel não lhe tirava nada: a sessão já
 * aberta continuava a ver o painel e a chamar toda a /api/admin/* até expirar
 * sozinha, até duas horas depois. E desativar é justamente a ação de resposta
 * a uma conta comprometida — a altura em que essas duas horas mais custam.
 *
 * Só 'ativo' é reconfirmado. O 'bloqueado_ate' do LoginThrottleService fica
 * de fora de propósito: esse campo marca tentativas falhadas contra a conta,
 * não uma decisão sobre ela. Expulsar a sessão a partir dele dava a qualquer
 * pessoa que soubesse o email de um administrador uma forma de o pôr fora do
 * painel a meio do trabalho, com cinco pedidos errados.
 */
class BloquearAdminInativo
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Administrador|null $admin */
        $admin = $request->user('admin');

        if ($admin && ! $admin->ativo) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 401 e não 403 como em BloquearUtilizadorSuspenso: a sessão
            // acabou de ser destruída acima, portanto quem faz o pedido já
            // não está autenticado. É também o único código que o painel
            // trata (kgApiJSON, em admin/painel.blade.php) — com 403 ficava
            // um painel morto a mostrar um erro, em vez de ir para o login.
            if ($request->expectsJson()) {
                return response()->json(
                    ['erro' => 'A sua conta de administrador foi desativada.'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
