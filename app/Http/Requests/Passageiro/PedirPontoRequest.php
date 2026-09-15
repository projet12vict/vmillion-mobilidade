<?php

declare(strict_types=1);

namespace App\Http\Requests\Passageiro;

use App\Rules\CoordenadaCaboVerde;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido intermunicipal em aberto: o passageiro escolheu o ponto de partida e
 * o destino da carreira, mas nenhum veículo — o pedido vai para todos os
 * condutores desse ponto (ver Passageiro\ReservaController::pedirPonto).
 */
class PedirPontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLugares = (int) config('vmillion.veiculos.lugares_max_por_reserva');

        return [
            'ponto_partida_id' => ['required', 'integer'],
            'destino_id' => ['required', 'integer'],
            'lugares' => ['nullable', 'integer', 'between:1,'.$maxLugares],
            'motivo' => ['nullable', 'in:normal,grupo,passeio'],
            // O ponto de descida continua opcional, como em qualquer viagem
            // intermunicipal: o destino da carreira já basta.
            'ponto_descida_nome' => ['nullable', 'string', 'max:200'],
            'ponto_descida_lat' => ['nullable', 'numeric', 'required_with:ponto_descida_lng', new CoordenadaCaboVerde('ponto_descida_lng')],
            'ponto_descida_lng' => ['nullable', 'numeric', 'required_with:ponto_descida_lat'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxLugares = (int) config('vmillion.veiculos.lugares_max_por_reserva');

        return [
            'ponto_partida_id.required' => 'Escolha o ponto de partida.',
            'destino_id.required' => 'Escolha o destino.',
            'lugares.between' => "Número de lugares inválido (1-{$maxLugares}).",
        ];
    }
}
