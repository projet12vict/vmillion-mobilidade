<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Support\FicheiroPrivado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recibo de um pagamento aprovado, visto do painel de admin: o botão "Ver
 * recibo" abre o PDF no browser (inline) e o "Descarregar" guarda o ficheiro
 * (?descarregar=1). Antes o endpoint era sempre "attachment" e "Ver recibo"
 * descarregava em vez de mostrar.
 */
class AdminReciboPagamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ver_recibo_serve_o_pdf_inline(): void
    {
        Storage::fake('local');
        $pagamento = $this->pagamentoComRecibo();

        $resposta = $this->actingAs($this->administrador(), 'admin')
            ->get('/api/admin/recibo?id='.$pagamento->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('inline;', $resposta->headers->get('Content-Disposition'));
        // O nome do ficheiro é o da referência, tanto a ver como a descarregar.
        $this->assertStringContainsString('recibo_REF-0001.pdf', $resposta->headers->get('Content-Disposition'));
    }

    public function test_descarregar_serve_o_mesmo_pdf_como_anexo(): void
    {
        Storage::fake('local');
        $pagamento = $this->pagamentoComRecibo();

        $resposta = $this->actingAs($this->administrador(), 'admin')
            ->get('/api/admin/recibo?id='.$pagamento->id.'&descarregar=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('attachment;', $resposta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('recibo_REF-0001.pdf', $resposta->headers->get('Content-Disposition'));
    }

    /**
     * O painel só mostra os botões do recibo em pagamentos aprovados, e a API
     * concorda: um pagamento revertido e depois recusado mantém o ficheiro
     * antigo em recibo_path, mas o recibo já não vale.
     */
    public function test_recibo_de_pagamento_nao_aprovado_devolve_404(): void
    {
        Storage::fake('local');
        $pagamento = $this->pagamentoComRecibo(['status' => 'recusado']);

        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/recibo?id='.$pagamento->id)
            ->assertStatus(404)
            ->assertJson(['erro' => 'Recibo não encontrado.']);
    }

    public function test_recibo_com_o_ficheiro_em_falta_devolve_404(): void
    {
        Storage::fake('local');
        // Registo aprovado com caminho, mas sem ficheiro no disco.
        $pagamento = $this->pagamentoComRecibo(gravarFicheiro: false);

        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/recibo?id='.$pagamento->id)
            ->assertStatus(404)
            ->assertJson(['erro' => 'Ficheiro do recibo não encontrado.']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function pagamentoComRecibo(array $atributos = [], bool $gravarFicheiro = true): PagamentoCondutor
    {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true,
        ]);

        $caminho = FicheiroPrivado::PASTA_RECIBOS.'/recibo_REF-0001.pdf';

        if ($gravarFicheiro) {
            Storage::disk('local')->put($caminho, '%PDF-1.4 recibo de teste');
        }

        return PagamentoCondutor::query()->create($atributos + [
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 5000,
            'referencia' => 'REF-0001',
            'status' => 'aprovado',
            'data_validade' => now()->addDays(30),
            'recibo_path' => $caminho,
        ]);
    }

    private function administrador(): Administrador
    {
        return Administrador::query()->create([
            'nome' => 'Admin',
            'email' => 'admin'.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'admin',
            'senha_temporaria' => false,
            'ativo' => true,
        ]);
    }
}
