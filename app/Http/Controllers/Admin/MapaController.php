<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parque;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Http\JsonResponse;

/**
 * Vista geral do "Editor de mapa" do painel de administração.
 *
 * É o mesmo mapa que o condutor vê, mas sem os recortes que no painel dele
 * fazem sentido (só a própria rota, só o próprio tipo de veículo, só o
 * próprio município): o administrador vê todos os veículos aprovados que têm
 * posição conhecida e todos os passageiros com pedido ou reserva em curso,
 * com os contactos dos dois lados. Os pontos de partida continuam a vir de
 * /api/admin/pontos, porque é esse endpoint que alimenta a edição.
 */
class MapaController extends Controller
{
    /** Parados no ponto: sem GPS próprio, a posição é a do ponto. */
    private const ESTADOS_PARADOS = ['no_ponto', 'na_fila'];

    /**
     * Mesmo limite do OutrosCondutoresController: acima disto a posição de
     * um veículo "a caminho" já não diz onde ele está. Aqui o veículo não é
     * escondido (o admin quer saber que ele existe), só vem marcado.
     */
    private const FRESCURA_POSICAO_MINUTOS = 15;

    public function __construct(private readonly VeiculoService $veiculos) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'veiculos' => $this->veiculos(),
            'passageiros' => $this->passageiros(),
            'parques' => Parque::query()->orderBy('nome')
                ->get(['id', 'nome', 'morada', 'cidade', 'lat', 'lng', 'capacidade_total', 'vagas_ocupadas']),
            'atualizado_em' => now()->toIso8601String(),
        ]);
    }

    private function veiculos(): array
    {
        $limiteFrescura = now()->subMinutes(self::FRESCURA_POSICAO_MINUTOS);

        return Veiculo::query()
            ->join('utilizadores as u', 'u.id', '=', 'veiculos.condutor_id')
            ->leftJoin('pontos_partida as pp', 'pp.id', '=', 'veiculos.ponto_partida_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'veiculos.destino_id')
            ->where('veiculos.aprovado', true)
            ->orderBy('veiculos.matricula')
            ->select([
                'veiculos.*',
                'pp.nome as ponto_partida_nome', 'pp.lat as ponto_partida_lat', 'pp.lng as ponto_partida_lng',
                'pd.nome as destino_nome',
                'u.nome as condutor_nome', 'u.telefone as condutor_telefone',
                'u.status as condutor_status', 'u.municipio as condutor_municipio',
            ])
            ->selectRaw('EXISTS (SELECT 1 FROM pagamentos_condutores pc WHERE pc.condutor_id = veiculos.condutor_id AND pc.status = ? AND pc.data_validade >= ?) as pagamento_em_dia', ['aprovado', now()])
            ->get()
            ->map(function (Veiculo $v) use ($limiteFrescura) {
                $lat = $v->lat;
                $lng = $v->lng;

                // Parado no ponto e ainda sem GPS gravado: desenha-se em cima
                // do ponto, como no painel do condutor.
                if (($lat === null || $lng === null) && in_array($v->estado, self::ESTADOS_PARADOS, true)) {
                    $lat = $v->ponto_partida_lat;
                    $lng = $v->ponto_partida_lng;
                }

                if ($lat === null || $lng === null) {
                    return null;
                }

                return [
                    'id' => $v->id,
                    'matricula' => $v->matricula,
                    'tipo' => $v->tipo,
                    'tipo_servico' => $v->tipo_servico,
                    'cor' => $v->cor,
                    'modelo' => $v->modelo,
                    'estado' => $v->estado,
                    'posicao_fila' => $v->posicao_fila,
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'lugares_livres' => $v->lugares_livres,
                    'ultima_posicao_em' => $v->ultima_posicao_em?->toIso8601String(),
                    'posicao_antiga' => $v->ultima_posicao_em === null || $v->ultima_posicao_em->lt($limiteFrescura),
                    'ponto_partida_nome' => $v->ponto_partida_nome,
                    'destino_nome' => $v->destino_nome,
                    'condutor_id' => $v->condutor_id,
                    'condutor_nome' => $v->condutor_nome,
                    'condutor_telefone' => $v->condutor_telefone,
                    'condutor_status' => $v->condutor_status,
                    'condutor_municipio' => $v->condutor_municipio,
                    'pagamento_em_dia' => (bool) $v->pagamento_em_dia,
                    'ocupacao' => $this->veiculos->ocupacao($v),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Quem ainda está fora de um carro: pedido em aberto, reserva aceite ou
     * condutor a caminho. Quem já vai a bordo aparece dentro do popup do
     * veículo (ocupação), como no mapa do condutor.
     */
    private function passageiros(): array
    {
        return Reserva::query()
            ->join('utilizadores as u', 'u.id', '=', 'reservas.passageiro_id')
            ->join('pontos_partida as pp', 'pp.id', '=', 'reservas.ponto_partida_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'reservas.destino_id')
            ->leftJoin('veiculos as v', 'v.id', '=', 'reservas.veiculo_id')
            ->leftJoin('utilizadores as c', 'c.id', '=', 'v.condutor_id')
            ->whereIn('reservas.estado', ['pendente', 'confirmado', 'a_caminho'])
            ->orderBy('reservas.criado_em')
            ->get([
                'reservas.id', 'reservas.estado', 'reservas.tipo_viagem', 'reservas.lugares',
                'reservas.preco_final', 'reservas.ponto_descida_nome', 'reservas.criado_em',
                'reservas.passageiro_lat', 'reservas.passageiro_lng', 'reservas.passageiro_localizacao_em',
                'u.id as passageiro_id', 'u.nome as passageiro_nome', 'u.telefone as passageiro_telefone',
                'pp.nome as ponto_partida_nome', 'pp.lat as ponto_partida_lat', 'pp.lng as ponto_partida_lng',
                'pd.nome as destino_nome',
                'v.id as veiculo_id', 'v.matricula as veiculo_matricula',
                'c.nome as condutor_nome', 'c.telefone as condutor_telefone',
            ])
            ->map(fn (Reserva $r) => [
                'id' => $r->id,
                'estado' => $r->estado,
                'tipo_viagem' => $r->tipo_viagem,
                'lugares' => $r->lugares,
                'preco_final' => $r->preco_final,
                'criado_em' => $r->criado_em?->toIso8601String(),
                'passageiro_id' => $r->passageiro_id,
                'passageiro_nome' => $r->passageiro_nome,
                'passageiro_telefone' => $r->passageiro_telefone,
                // GPS ao vivo quando o telemóvel já o enviou; senão o ponto
                // de partida escolhido (mesma regra do mapa do condutor).
                'lat' => (float) ($r->passageiro_lat ?? $r->ponto_partida_lat),
                'lng' => (float) ($r->passageiro_lng ?? $r->ponto_partida_lng),
                'com_gps' => $r->passageiro_lat !== null && $r->passageiro_lng !== null,
                'localizacao_em' => $r->passageiro_localizacao_em?->toIso8601String(),
                'ponto_partida_nome' => $r->ponto_partida_nome,
                'destino_nome' => $r->ponto_descida_nome ?? $r->destino_nome,
                'veiculo_id' => $r->veiculo_id,
                'veiculo_matricula' => $r->veiculo_matricula,
                'condutor_nome' => $r->condutor_nome,
                'condutor_telefone' => $r->condutor_telefone,
            ])
            ->all();
    }
}
