<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class RegistoViewTest extends TestCase
{
    public function test_a_pagina_de_registo_desenha_o_seletor_de_pais(): void
    {
        $html = $this->get('/registo')->assertOk()->getContent();

        $this->assertStringContainsString('data-tel-botao', $html);
        $this->assertStringContainsString('/assets/js/vm-telefone.js', $html);
        $this->assertStringContainsString('name="telefone"', $html);
        $this->assertSame(count(config('paises')), substr_count($html, 'data-tel-opcao'));
        $this->assertStringContainsString('data-iso="PT"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
    }

    public function test_a_pagina_de_registo_pede_ilha_e_municipio(): void
    {
        $html = $this->get('/registo')->assertOk()->getContent();

        $this->assertStringContainsString('name="ilha"', $html);
        $this->assertStringContainsString('name="municipio"', $html);

        foreach (array_keys(config('municipios')) as $ilha) {
            $this->assertStringContainsString('<option value="'.e($ilha).'">', $html);
        }
    }
}
