<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Administrador;
use App\Models\Notificacao;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Support\FicheiroPrivado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Aprovação de um pagamento de condutor: emite o recibo, abre o acesso pela
 * duração do pacote, ativa a conta de um condutor novo e avisa-o.
 *
 * Tem dois chamadores: o admin, ao aprovar um pedido com comprovativo, e o
 * próprio pedido do pacote de teste (preço 0), aprovado logo sem admin — por
 * isso $admin é opcional. Viver aqui garante que os dois caminhos dão o mesmo
 * resultado em vez de a regra de "aprovado" se repetir em dois controllers.
 */
class AprovacaoPagamentoService
{
    /** Pagamentos anteriores aos pacotes (taxa fixa) mantêm os 30 dias de sempre. */
    private const DURACAO_PADRAO_DIAS = 30;

    public function __construct(
        private readonly ReciboPdfService $recibos,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * Deve correr dentro de uma transação, com o pagamento ainda 'pendente'.
     *
     * @return string Caminho do recibo gerado.
     */
    public function aprovar(PagamentoCondutor $pagamento, ?Administrador $admin, ?string $observacao = null): string
    {
        $contexto = $this->contextoDoPagamento($pagamento);

        $duracaoDias = $contexto->pacote_duracao_dias !== null
            ? (int) $contexto->pacote_duracao_dias
            : self::DURACAO_PADRAO_DIAS;

        $dataPagamento = now();
        $dataValidade = $dataPagamento->copy()->addDays($duracaoDias);

        $reciboPath = $this->gerarRecibo($pagamento, $contexto, $admin, $duracaoDias, $dataPagamento, $dataValidade);

        $pagamento->forceFill([
            'status' => 'aprovado',
            'aprovado_por' => $admin?->id,
            'aprovado_em' => now(),
            'data_pagamento' => $dataPagamento,
            'data_validade' => $dataValidade,
            'recibo_path' => $reciboPath,
            'observacao_admin' => $observacao,
        ])->save();

        $this->auditoria->registar(
            $admin?->id, 'aprovou_pagamento_condutor', 'pagamentos_condutores',
            $pagamento->id, $pagamento->referencia.($admin ? '' : ' (pacote de teste, aprovado automaticamente)')
        );

        // Um condutor novo fica 'pendente' até ter um pagamento aprovado e
        // válido. Aprovar aqui o primeiro pagamento é o que desbloqueia a
        // conta — sem isto ficava à espera de alguém o aprovar noutro ecrã.
        $ativouConta = $contexto->condutor_status === 'pendente';

        if ($ativouConta) {
            Utilizador::query()
                ->whereKey($pagamento->condutor_id)
                ->where('tipo', 'condutor')
                ->update(['status' => 'ativo']);

            $this->auditoria->registar(
                $admin?->id, 'aprovou_condutor', 'utilizadores', $pagamento->condutor_id,
                'ativado automaticamente ao aprovar pagamento '.$pagamento->referencia
            );
        }

        Notificacao::query()->create([
            'destinatario_id' => $pagamento->condutor_id,
            'destinatario_tipo' => 'individual',
            'remetente_id' => $admin?->id,
            'titulo' => $ativouConta ? 'Conta ativada' : 'Pagamento aprovado',
            'mensagem' => "O seu pagamento (ref. {$pagamento->referencia}) foi aprovado. Acesso válido até "
                .$dataValidade->format('Y-m-d H:i:s').'.'
                .($ativouConta ? ' A sua conta foi ativada com sucesso — já pode aceder ao mapa.' : ''),
            'tipo' => 'informativo',
        ]);

        return $reciboPath;
    }

    /**
     * Nome do condutor, matrícula, rota e pacote — tudo o que o recibo
     * precisa e que não vive na própria linha do pagamento.
     */
    private function contextoDoPagamento(PagamentoCondutor $pagamento): object
    {
        return PagamentoCondutor::query()
            ->join('utilizadores as u', 'u.id', '=', 'pagamentos_condutores.condutor_id')
            ->join('veiculos as v', 'v.id', '=', 'pagamentos_condutores.veiculo_id')
            ->leftJoin('precos_rotas as pr', 'pr.id', '=', 'pagamentos_condutores.rota_id')
            ->leftJoin('pontos_partida as po', 'po.id', '=', 'pr.ponto_origem_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'pr.ponto_destino_id')
            ->leftJoin('pacotes_pagamento as pac', 'pac.id', '=', 'pagamentos_condutores.pacote_id')
            ->whereKey($pagamento->id)
            ->first([
                'u.nome as condutor_nome', 'u.status as condutor_status',
                'v.matricula',
                'po.nome as origem_nome', 'pd.nome as destino_nome',
                'pac.nome as pacote_nome',
                'pac.duracao_dias as pacote_duracao_dias',
                'pac.tipo_servico as pacote_tipo_servico',
            ]);
    }

    private function gerarRecibo(
        PagamentoCondutor $pagamento,
        object $contexto,
        ?Administrador $admin,
        int $duracaoDias,
        Carbon $dataPagamento,
        Carbon $dataValidade
    ): string {
        $pdf = $this->recibos->gerar($this->recibos->linhasReciboCondutor([
            'referencia' => $pagamento->referencia,
            'condutor_nome' => $contexto->condutor_nome,
            'matricula' => $contexto->matricula,
            'rota_texto' => $contexto->origem_nome
                ? "{$contexto->origem_nome} -> {$contexto->destino_nome}"
                : 'Sem rota fixa associada',
            'valor_pago' => (float) $pagamento->valor_pago,
            'data_pagamento' => $dataPagamento->format('Y-m-d H:i:s'),
            'data_validade' => $dataValidade->format('Y-m-d H:i:s'),
            'aprovado_por' => $admin?->nome ?? 'Automático (pacote de teste)',
            'pacote_nome' => $contexto->pacote_nome,
            'duracao_dias' => $duracaoDias,
            'tipo_servico' => $contexto->pacote_tipo_servico,
        ]));

        $caminho = FicheiroPrivado::PASTA_RECIBOS.'/recibo_'.$pagamento->referencia.'.pdf';
        Storage::disk('local')->put($caminho, $pdf);

        return $caminho;
    }
}
