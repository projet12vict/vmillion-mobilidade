<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pagina;

use App\Http\Controllers\Controller;
use App\Models\Proprietario;
use App\Services\VeiculoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Páginas HTML da aplicação. Toda a interação com dados continua a acontecer
 * por fetch para /api/*: estas ações só resolvem o que a página precisa de
 * saber no primeiro render (identidade, gates de acesso, tipo de conta).
 */
class PaginaController extends Controller
{
    public function inicio(): View
    {
        return view('inicio');
    }

    public function login(Request $request): View|RedirectResponse
    {
        if ($utilizador = $request->user('utilizador')) {
            return redirect()->route($utilizador->isCondutor() ? 'condutor.painel' : 'passageiro.painel');
        }

        $tipo = in_array($request->query('tipo'), ['condutor', 'passageiro'], true)
            ? $request->query('tipo')
            : '';

        return view('auth.login', [
            'tipo' => $tipo,
            'linkRegisto' => $tipo ? route('registo', ['tipo' => $tipo]) : route('registo'),
            // A sessão pode ter sido terminada a meio por uma suspensão (ver
            // BloquearUtilizadorSuspenso); o ?suspenso=1 antigo continua a
            // funcionar para links já partilhados.
            'mensagemSuspenso' => ($request->session()->get('suspenso') || $request->query('suspenso'))
                ? 'A sua conta foi suspensa enquanto estava com sessão iniciada. Contacte o administrador.'
                : '',
        ]);
    }

    public function registo(Request $request): View
    {
        $tipoInicial = in_array($request->query('tipo'), ['condutor', 'passageiro'], true)
            ? $request->query('tipo')
            : 'passageiro';

        return view('auth.registo', [
            'tipoInicial' => $tipoInicial,
            'municipios' => config('municipios'),
        ]);
    }

    public function loginAdmin(Request $request): View|RedirectResponse
    {
        if ($request->user('admin')) {
            return redirect()->route('admin.painel');
        }

        return view('admin.login');
    }

    public function painelPassageiro(Request $request): View
    {
        return view('passageiro.painel', [
            'passageiro' => $request->user('utilizador'),
            'maxLugares' => (int) config('vmillion.veiculos.lugares_max_por_reserva'),
        ]);
    }

    public function painelCondutor(Request $request, VeiculoService $veiculos): View
    {
        $condutor = $request->user('utilizador');

        return view('condutor.painel', [
            'condutor' => $condutor,
            // Gate de pagamento (secção 11.6): o condutor só vê o mapa e os
            // passageiros com uma taxa de operação aprovada e válida.
            'mostrarMapa' => $veiculos->condutorPodeVerMapa($condutor->id),
            // Papel duplo (secção 8): este condutor também é dono de frota?
            'proprietarioAssociado' => Proprietario::query()
                ->where('utilizador_condutor_id', $condutor->id)
                ->value('nome'),
        ]);
    }

    public function painelAdmin(Request $request): View
    {
        $admin = $request->user('admin');

        // Uma conta criada pelo Super Admin nasce com senha temporária e não
        // chega aqui sem a trocar — quem a manda trocar é o middleware
        // 'senha_definitiva', que cobre também a /api/admin/*.
        return view('admin.painel', ['admin' => $admin, 'ehSuper' => $admin->isSuper()]);
    }

    public function trocarSenhaAdmin(Request $request): View
    {
        return view('admin.trocar-senha', ['admin' => $request->user('admin')]);
    }

    /**
     * Logout partilhado: termina a sessão de quem estiver autenticado
     * (utilizador ou admin) e devolve à landing page.
     */
    public function logout(Request $request): RedirectResponse
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
}
