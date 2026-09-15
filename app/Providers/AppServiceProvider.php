<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Asset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->limitadorDeLogin();
        $this->limitadorDeRegisto();
        $this->regraDeSenha();
        $this->versaoDosAssets();
    }

    /**
     * @kgAsset('assets/js/kg-chamada.js') — o mesmo URL com a data de
     * modificação do ficheiro no fim, para que uma versão nova não fique
     * escondida atrás da cópia que o browser já tem (ver App\Support\Asset).
     */
    private function versaoDosAssets(): void
    {
        Blade::directive('kgAsset', function (string $expressao): string {
            return '<?php echo e('.Asset::class."::url({$expressao})); ?>";
        });
    }

    /**
     * Limitador 'login', aplicado a /api/auth/login e /api/auth/login_admin.
     *
     * Complementa o bloqueio por conta do LoginThrottleService, que só travava
     * ataques contra uma conta de cada vez: contar também por IP é o que trava
     * password spraying (uma senha comum testada contra milhares de telefones,
     * sem nunca esgotar as tentativas de nenhuma conta).
     *
     * A contagem por identificador é feita sobre o valor tal como foi escrito,
     * em minúsculas e sem espaços, para que "+238 9912345" e "+2389912345"
     * caiam no mesmo contador.
     */
    private function limitadorDeLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $limites = config('vmillion.login.throttle');

            return [
                Limit::perMinute($limites['ip_por_minuto'])
                    ->by('ip:'.$request->ip())
                    ->response($this->respostaDemasiadasTentativas()),

                Limit::perHour($limites['ip_por_hora'])
                    ->by('ip-hora:'.$request->ip())
                    ->response($this->respostaDemasiadasTentativas()),

                Limit::perMinute($limites['conta_por_minuto'])
                    ->by('conta:'.$this->chaveDoIdentificador($request))
                    ->response($this->respostaDemasiadasTentativas()),
            ];
        });
    }

    /**
     * Limitador 'registo', aplicado a /api/auth/registar.
     *
     * Criar conta é um ato raro e caro do lado do servidor (um hash bcrypt por
     * pedido), por isso conta-se só por IP — não há aqui um identificador de
     * conta existente que faça sentido usar como chave.
     *
     * Sem este limite, o 409 de telefone/NIF duplicado era um oráculo de
     * enumeração gratuito: nove dígitos de NIF varrem-se depressa quando cada
     * tentativa custa um pedido.
     */
    private function limitadorDeRegisto(): void
    {
        RateLimiter::for('registo', function (Request $request) {
            $limites = config('vmillion.registo.throttle');

            return [
                Limit::perMinute($limites['ip_por_minuto'])
                    ->by('registo-ip:'.$request->ip())
                    ->response($this->respostaDemasiadasTentativas()),

                Limit::perHour($limites['ip_por_hora'])
                    ->by('registo-ip-hora:'.$request->ip())
                    ->response($this->respostaDemasiadasTentativas()),
            ];
        });
    }

    /**
     * Regra de senha usada em todo o sistema (Password::defaults()).
     *
     * O comprimento mínimo, sozinho, deixava passar '12345678' e 'senha1234'.
     * O uncompromised() compara a senha com a lista de fugas conhecidas
     * (api.pwnedpasswords.com, por k-anonimato: só os cinco primeiros
     * caracteres do hash SHA-1 saem daqui, nunca a senha).
     *
     * A verificação falha para o lado permissivo — se a API não responder, a
     * senha é aceite. É o comportamento certo para Cabo Verde, onde a ligação
     * cai: mais vale aceitar uma senha por verificar do que impedir alguém de
     * criar conta. Nos testes fica de fora para não os pôr a depender da rede.
     */
    private function regraDeSenha(): void
    {
        Password::defaults(function () {
            $regra = Password::min((int) config('vmillion.validacao.senha_min_len'));

            return app()->runningUnitTests() ? $regra : $regra->uncompromised();
        });
    }

    /**
     * O login de utilizadores manda 'identificador' (telefone ou email) e o de
     * administradores manda 'email'.
     */
    private function chaveDoIdentificador(Request $request): string
    {
        $identificador = $request->input('identificador') ?? $request->input('email');

        if (! is_string($identificador) || trim($identificador) === '') {
            return 'sem-identificador';
        }

        return hash('sha256', Str::lower(preg_replace('/\s+/', '', $identificador)));
    }

    /**
     * Mesma mensagem do bloqueio por conta: se a resposta do limite por pedido
     * fosse diferente, comparar as duas dizia a quem estivesse a tentar se a
     * conta existe.
     */
    private function respostaDemasiadasTentativas(): callable
    {
        return function (Request $request, array $cabecalhos): JsonResponse {
            $minutos = max(1, (int) ceil(((int) ($cabecalhos['Retry-After'] ?? 60)) / 60));

            return response()->json(
                ['erro' => "Demasiadas tentativas. Tente novamente em {$minutos} min."],
                429,
                $cabecalhos
            );
        };
    }
}
