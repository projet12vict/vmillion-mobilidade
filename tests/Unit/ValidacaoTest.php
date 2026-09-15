<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Telefone;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validações de dados de conta. Porta do bloco de validações de
 * legacy/tests/run_tests.php.
 */
class ValidacaoTest extends TestCase
{
    public function test_aceita_telefone_de_cabo_verde_com_e_sem_espaco(): void
    {
        $this->assertTrue(Telefone::valido('+2389912345'));
        $this->assertTrue(Telefone::valido('+238 9912345'));
    }

    public function test_rejeita_telefone_sem_indicativo_ou_com_digitos_a_menos(): void
    {
        $this->assertFalse(Telefone::valido('9912345'));
        $this->assertFalse(Telefone::valido('+238991234'));
    }

    public function test_normaliza_o_telefone_removendo_o_espaco_do_indicativo(): void
    {
        $this->assertSame('+2389912345', Telefone::normalizar('+238 9912345'));
        $this->assertSame('+2389912345', Telefone::normalizar('+2389912345'));
    }

    public function test_nif_tem_de_ter_exatamente_nove_digitos(): void
    {
        $this->assertTrue($this->nifValido('123456789'));
        $this->assertFalse($this->nifValido('12345678'));
        $this->assertFalse($this->nifValido('12345678A'));
    }

    public function test_senha_tem_no_minimo_oito_caracteres(): void
    {
        $minimo = (int) config('vmillion.validacao.senha_min_len');

        $this->assertTrue($this->senhaValida(str_repeat('1', $minimo)));
        $this->assertFalse($this->senhaValida(str_repeat('1', $minimo - 1)));
    }

    private function nifValido(string $nif): bool
    {
        return Validator::make(
            ['nif' => $nif],
            ['nif' => ['regex:'.config('vmillion.validacao.regex_nif')]]
        )->passes();
    }

    private function senhaValida(string $senha): bool
    {
        return Validator::make(
            ['senha' => $senha],
            ['senha' => ['min:'.config('vmillion.validacao.senha_min_len')]]
        )->passes();
    }
}
