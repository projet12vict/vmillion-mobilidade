<?php

declare(strict_types=1);

namespace App\Http\Requests\Passageiro;

use App\Rules\CoordenadaCaboVerde;
use Illuminate\Foundation\Http\FormRequest;

class PedirUrbanoRequest extends FormRequest
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
            'origem_lat' => ['required', 'numeric', new CoordenadaCaboVerde('origem_lng')],
            'origem_lng' => ['required', 'numeric'],
            'ponto_descida_nome' => ['required', 'string', 'max:200'],
            'ponto_descida_lat' => ['required', 'numeric', new CoordenadaCaboVerde('ponto_descida_lng')],
            'ponto_descida_lng' => ['required', 'numeric'],
            'lugares' => ['nullable', 'integer', 'between:1,'.$maxLugares],
            'motivo' => ['nullable', 'in:normal,grupo,passeio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxLugares = (int) config('vmillion.veiculos.lugares_max_por_reserva');

        return [
            'ponto_partida_id.required' => 'Indique a sua localização atual.',
            'origem_lat.required' => 'Indique a sua localização atual.',
            'origem_lng.required' => 'Indique a sua localização atual.',
            'ponto_descida_nome.required' => 'Escreva o destino.',
            'ponto_descida_lat.required' => 'Escreva o destino.',
            'ponto_descida_lng.required' => 'Escreva o destino.',
            'lugares.between' => "Número de lugares inválido (1-{$maxLugares}).",
        ];
    }
}
