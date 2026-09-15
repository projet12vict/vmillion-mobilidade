<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chamada;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;

/**
 * Regras de quem pode chamar quem, e limpeza de chamadas paradas.
 * Porta de includes/chamadas.php.
 */
class ChamadaService
{
    /**
     * Mesma regra de relação já usada no autofalante (secção 6): só entre
     * condutor e passageiro com reserva ativa no mesmo veículo — nunca entre
     * dois utilizadores sem nenhuma viagem em comum.
     */
    public function podeChamar(Utilizador $remetente, int $destinatarioId): bool
    {
        if ($remetente->id === $destinatarioId) {
            return false;
        }

        return match ($remetente->tipo) {
            'condutor' => $this->existeViagemComum($destinatarioId, $remetente->id),
            'passageiro' => $this->existeViagemComum($remetente->id, $destinatarioId),
            default => false,
        };
    }

    private function existeViagemComum(int $passageiroId, int $condutorId): bool
    {
        return Reserva::query()
            ->ativas()
            ->where('passageiro_id', $passageiroId)
            ->whereIn('veiculo_id', Veiculo::query()->where('condutor_id', $condutorId)->select('id'))
            ->exists();
    }

    /**
     * Chamadas 'iniciada' a que ninguém respondeu ficariam para sempre a
     * bloquear novas chamadas para o mesmo destinatário — isto fecha-as como
     * perdidas ao fim do timeout configurado, sempre que a tabela é
     * consultada (sem precisar de cron).
     *
     * O mesmo vale para as 'atendida': o /desligar é um clique, e um browser
     * fechado ou recarregado a meio da chamada nunca o manda. Sem isto, essa
     * linha ficava 'atendida' para sempre e os dois participantes passavam a
     * receber 'ocupado' em qualquer chamada futura, sem forma de recuperar
     * pela app. A prova de vida é o pulso que o painel manda enquanto a
     * chamada está viva (ChamadaController::pulso), que toca em
     * atualizada_em; passada a folga sem nenhum pulso, já não há ninguém
     * do outro lado.
     */
    public function expirarChamadasParadas(): void
    {
        Chamada::query()
            ->where('estado', 'iniciada')
            ->where('iniciada_em', '<', now()->subSeconds((int) config('vmillion.chamadas.timeout_segundos')))
            ->update(['estado' => 'terminada', 'terminada_em' => now()]);

        Chamada::query()
            ->where('estado', 'atendida')
            ->where('atualizada_em', '<', now()->subSeconds((int) config('vmillion.chamadas.pulso_timeout_segundos')))
            ->update(['estado' => 'terminada', 'terminada_em' => now()]);
    }

    /**
     * Devolve a chamada só se o utilizador for um dos dois participantes —
     * é isto que garante que ninguém lê nem escreve ofertas, respostas ou
     * candidatos ICE de uma chamada alheia.
     */
    public function chamadaDoParticipante(int $chamadaId, int $utilizadorId): ?Chamada
    {
        return Chamada::query()
            ->where('id', $chamadaId)
            ->where(function ($q) use ($utilizadorId) {
                $q->where('remetente_id', $utilizadorId)->orWhere('destinatario_id', $utilizadorId);
            })
            ->first();
    }
}
