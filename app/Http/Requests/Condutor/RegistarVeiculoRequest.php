<?php

declare(strict_types=1);

namespace App\Http\Requests\Condutor;

use Illuminate\Foundation\Http\FormRequest;

class RegistarVeiculoRequest extends FormRequest
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
            'matricula' => ['required', 'string', 'max:20'],
            'tipo' => ['required', 'in:hiace,taxi,autocarro'],
            'tipo_servico' => ['nullable', 'in:urbano,intermunicipal,ambos'],
            'cor' => ['required', 'string', 'max:40'],
            'modelo' => ['required', 'string', 'max:80'],
            // O teto depende do tipo e é verificado no controller, com
            // VeiculoService::lugaresMax. Sem o campo, vale a capacidade do
            // tipo, como antes de o formulário o ter.
            'lugares' => ['nullable', 'integer', 'min:1'],
            'rota_fixa_id' => ['nullable', 'integer', 'exists:precos_rotas,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matricula.required' => 'Indique a matrícula.',
            'tipo.required' => 'Tipo inválido.',
            'tipo.in' => 'Tipo inválido.',
            'cor.required' => 'Indique a cor.',
            'modelo.required' => 'Indique o modelo.',
            'lugares.integer' => 'Indique o número de lugares em algarismos.',
            'lugares.min' => 'O veículo tem de ter pelo menos 1 lugar.',
            'rota_fixa_id.exists' => 'Rota fixa não encontrada.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Um campo vazio no formulário chega como "" e não como null; sem
        // isto, 'rota_fixa_id' vazio falhava a regra 'exists' em vez de ser
        // tratado como "sem rota fixa associada".
        foreach (['rota_fixa_id', 'lugares'] as $campo) {
            if ($this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }

        foreach (['cor', 'modelo'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => trim((string) $this->input($campo))]);
            }
        }
    }
}
