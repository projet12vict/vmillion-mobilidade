<?php

declare(strict_types=1);

namespace App\Http\Requests\Passageiro;

use App\Rules\CoordenadaCaboVerde;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReservarRequest extends FormRequest
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
        return [
            'veiculo_id' => ['required', 'integer'],
            'assento_id' => ['required', 'integer'],
            'ponto_partida_id' => ['required', 'integer'],
            // Numa viagem urbana o destino não vem de uma lista, por isso
            // destino_id só é obrigatório fora desse caso (ver withValidator).
            'destino_id' => ['nullable', 'integer'],
            'tipo_viagem' => ['nullable', 'in:urbano,intermunicipal'],
            'lugares' => ['nullable', 'integer', 'between:1,'.self::maxLugares()],
            'motivo' => ['nullable', 'in:normal,grupo,passeio'],
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
        return [
            'veiculo_id.required' => 'Dados de reserva incompletos.',
            'assento_id.required' => 'Dados de reserva incompletos.',
            'ponto_partida_id.required' => 'Dados de reserva incompletos.',
            'lugares.between' => 'Número de lugares inválido (1-'.self::maxLugares().').',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $urbano = $this->input('tipo_viagem') === 'urbano';

            if ($urbano) {
                if (trim((string) $this->input('ponto_descida_nome')) === ''
                    || $this->input('ponto_descida_lat') === null
                    || $this->input('ponto_descida_lng') === null) {
                    $validator->errors()->add('ponto_descida_nome', 'Escreva o destino (viagem urbana).');
                }

                return;
            }

            if (! $this->filled('destino_id')) {
                $validator->errors()->add('destino_id', 'Dados de reserva incompletos.');
            }
        });
    }

    private static function maxLugares(): int
    {
        return (int) config('vmillion.veiculos.lugares_max_por_reserva');
    }
}
