<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A CSP é a última barreira quando alguma coisa escapa ao esc() dos painéis.
 * Estes testes fixam as duas decisões que a tornam útil, para que não voltem
 * atrás sem ser de propósito.
 */
class CabecalhosSegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_csp_nao_autoriza_scripts_de_cdns_publicas(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        preg_match('/script-src ([^;]+);/', (string) $csp, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringContainsString("'self'", $scriptSrc);
        $this->assertStringContainsString("'nonce-", $scriptSrc);

        // unpkg.com serve qualquer pacote do npm: autorizá-lo em script-src era
        // dar a qualquer injeção de HTML uma origem legítima de onde carregar
        // código. O Leaflet e o socket.io passaram a ser servidos por nós.
        $this->assertStringNotContainsString('unpkg.com', $scriptSrc);
        $this->assertStringNotContainsString('cdn.socket.io', $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-eval'", $scriptSrc);
    }

    public function test_a_csp_restringe_o_destino_dos_formularios(): void
    {
        // Sem form-action (que default-src não cobre), um formulário injetado
        // podia enviar o que o utilizador escrevesse para fora.
        $this->get('/')->assertOk()
            ->assertHeaderMissing('X-Powered-By');

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_os_paineis_carregam_o_esc_e_servem_o_leaflet_de_casa(): void
    {
        $passageiro = Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => 'Maria Semedo', 'telefone' => '+2389912345',
            'nif' => '123456789', 'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $html = $this->actingAs($passageiro, 'utilizador')
            ->get(route('passageiro.painel'))
            ->assertOk()
            ->getContent();

        // Sem o kg-esc.js carregado, todas as chamadas a esc() nos painéis
        // rebentam e as tabelas ficam vazias — é uma dependência real, não um
        // extra opcional.
        $this->assertStringContainsString('/assets/js/kg-esc.js', $html);
        $this->assertStringContainsString('/assets/vendor/leaflet/leaflet.js', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('cdn.socket.io', $html);
    }
}
