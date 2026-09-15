<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\GeoService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um par lat/lng contra os limites reais de Cabo Verde (secção 5.3).
 *
 * Latitude e longitude só fazem sentido validadas em conjunto — uma latitude
 * válida com uma longitude do outro lado do Atlântico continua a ser um ponto
 * inválido. Por isso a regra é declarada no campo da latitude e recebe o nome
 * do campo da longitude:
 *
 *   'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
 *   'lng' => ['required', 'numeric'],
 */
class CoordenadaCaboVerde implements ValidationRule
{
    public function __construct(private readonly string $campoLongitude) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $longitude = request()->input($this->campoLongitude);

        if (! is_numeric($value) || ! is_numeric($longitude)) {
            return; // A regra 'numeric' de cada campo já reporta este caso.
        }

        if (! app(GeoService::class)->coordenadasValidas((float) $value, (float) $longitude)) {
            $fail('Coordenadas fora dos limites de Cabo Verde.');
        }
    }
}
