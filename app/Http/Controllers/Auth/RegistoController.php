<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistoRequest;
use App\Models\Utilizador;
use App\Services\AuditoriaService;
use App\Support\Telefone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

/**
 * Registo de passageiro ou condutor no formulário único (secção 4.1).
 *
 * Um condutor entra como 'pendente': a conta só fica ativa depois de o
 * administrador aprovar, mediante comprovativo de pagamento.
 */
class RegistoController extends Controller
{
    /**
     * Resposta única para telefone e NIF já usados.
     *
     * Dizer qual dos dois colidiu transformava este endpoint num serviço de
     * consulta: com um NIF e uma resposta que separasse os casos, qualquer
     * pessoa confirmava se um NIF concreto tem conta aqui. O volume está
     * travado pelo limitador 'registo' (ver AppServiceProvider).
     */
    private const DUPLICADO = 'Não foi possível criar a conta com estes dados. Se já tem conta, inicie sessão ou contacte o suporte.';

    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {}

    public function registar(RegistoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $telefone = Telefone::normalizar($dados['telefone']);

        $jaExiste = Utilizador::query()
            ->where('telefone', $telefone)
            ->orWhere('nif', $dados['nif'])
            ->exists();

        if ($jaExiste) {
            return response()->json(['erro' => self::DUPLICADO], 409);
        }

        $ehCondutor = $dados['tipo'] === 'condutor';

        try {
            $utilizador = Utilizador::query()->create([
                'tipo' => $dados['tipo'],
                'nome' => $dados['nome'],
                'telefone' => $telefone,
                'nif' => $dados['nif'],
                'ilha' => $dados['ilha'],
                'municipio' => $dados['municipio'],
                'senha_hash' => $dados['senha'],
                'status' => $ehCondutor ? 'pendente' : 'ativo',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dois pedidos com o mesmo telefone/NIF ao mesmo tempo passam os
            // dois pelo exists() acima; quem chega em segundo bate no UNIQUE da
            // tabela. Sem este catch, a corrida devolvia 500 em vez do 409 que
            // o caso realmente é.
            return response()->json(['erro' => self::DUPLICADO], 409);
        }

        // Sem rasto do registo, uma campanha de criação de contas em massa não
        // deixava nada para investigar. Os detalhes são texto fixo, como nas
        // falhas de login: nada do que veio no formulário entra nos logs.
        $this->auditoria->registar(
            null,
            'conta_criada',
            'utilizadores',
            $utilizador->id,
            $ehCondutor ? 'condutor (pendente)' : 'passageiro'
        );

        return response()->json([
            'sucesso' => true,
            'mensagem' => $ehCondutor
                ? 'Registo efetuado. A sua conta fica ativa após aprovação do administrador (mediante comprovativo de pagamento).'
                : 'Registo efetuado com sucesso. Pode iniciar sessão.',
        ]);
    }
}
