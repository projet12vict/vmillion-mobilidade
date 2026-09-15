<?php

declare(strict_types=1);

namespace App\Http\Requests\Passageiro;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarPerfilRequest extends FormRequest
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
            'nome' => ['required', 'string', 'min:3', 'max:150'],
            'telefone' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_telefone')],
            'nif' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_nif')],
            'senha_atual' => ['nullable', 'string'],
            'nova_senha' => ['nullable', 'string', 'min:'.config('vmillion.validacao.senha_min_len')],
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
            'telefone.required' => 'Telefone inválido.',
            'telefone.regex' => 'Telefone inválido.',
            'nif.required' => 'NIF inválido (9 dígitos).',
            'nif.regex' => 'NIF inválido (9 dígitos).',
            'nova_senha.min' => 'A nova senha deve ter no mínimo 8 caracteres.',
        ];
    }
}
