<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistoRequest extends FormRequest
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
        // O município tem de pertencer à ilha escolhida: validar cada um contra
        // a lista inteira deixava passar "Santiago / São Vicente".
        $ilha = $this->input('ilha');
        $municipiosDaIlha = is_string($ilha) ? (config('municipios')[$ilha] ?? []) : [];

        return [
            // O 'not_regex' não é o que protege os painéis — isso é o esc() do
            // lado do browser, ver resources/views/*/painel.blade.php. É uma
            // segunda linha, e é barata: nenhum nome de pessoa leva '<' ou '>'.
            'nome' => ['required', 'string', 'min:3', 'max:150', 'not_regex:/[<>]/'],
            'telefone' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_telefone')],
            'nif' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_nif')],
            'ilha' => ['required', 'string', Rule::in(array_keys(config('municipios')))],
            'municipio' => ['required', 'string', Rule::in($municipiosDaIlha)],
            'senha' => ['required', 'string', Password::defaults(), 'confirmed'],
            'tipo' => ['required', 'in:passageiro,condutor'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Indique o nome completo.',
            'nome.min' => 'Indique o nome completo.',
            'nome.not_regex' => 'O nome não pode conter os caracteres < ou >.',
            'telefone.required' => 'Telefone inválido. Formato esperado: +238 9912345.',
            'telefone.regex' => 'Telefone inválido. Formato esperado: +238 9912345.',
            'nif.required' => 'NIF inválido. Deve ter exatamente 9 dígitos.',
            'nif.regex' => 'NIF inválido. Deve ter exatamente 9 dígitos.',
            'ilha.required' => 'Escolha a ilha.',
            'ilha.in' => 'Ilha inválida.',
            'municipio.required' => 'Escolha o município.',
            'municipio.in' => 'O município escolhido não pertence a essa ilha.',
            'senha.required' => 'A senha deve ter no mínimo 8 caracteres.',
            'senha.min' => 'A senha deve ter no mínimo 8 caracteres.',
            // A mensagem do uncompromised não pode ficar aqui: a regra Password
            // falha com a chave de tradução e não chega a consultar este
            // array. Está em lang/en/validation.php.
            'senha.confirmed' => 'As senhas não coincidem.',
            'tipo.required' => 'Tipo de utilizador inválido.',
            'tipo.in' => 'Tipo de utilizador inválido.',
        ];
    }

    /**
     * O formulário envia 'confirmar_senha'; a regra 'confirmed' do Laravel
     * espera 'senha_confirmation'. Renomear aqui evita mexer no HTML do
     * formulário e mantém a mensagem de erro em português.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('confirmar_senha')) {
            $this->merge(['senha_confirmation' => $this->input('confirmar_senha')]);
        }

        if ($this->has('telefone')) {
            $this->merge(['telefone' => trim((string) $this->input('telefone'))]);
        }
    }
}
