<?php
/**
 * V-MILLION — Testes automatizados de lógica pura (sem base de dados).
 * Corre com: C:\xampp\php\php.exe tests\run_tests.php
 * Cobre validações, geo, layout de assentos e o exemplo de preços da secção 12.3.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/geo_validation.php';
require_once __DIR__ . '/../includes/veiculos.php';

$falhas = 0;
$total = 0;

function kg_teste(string $nome, bool $condicao): void
{
    global $falhas, $total;
    $total++;
    if ($condicao) {
        echo "  OK  {$nome}\n";
    } else {
        echo "FALHA {$nome}\n";
        $falhas++;
    }
}

echo "== Validação de telefone (+238) ==\n";
kg_teste('aceita +2389912345', kg_validar_telefone('+2389912345'));
kg_teste('aceita +238 991 2345 (com espaço apos +238)', kg_validar_telefone('+238 9912345'));
kg_teste('rejeita sem indicativo', !kg_validar_telefone('9912345'));
kg_teste('rejeita com menos dígitos', !kg_validar_telefone('+238991234'));
kg_teste('normaliza +238 9912345 -> +2389912345', kg_normalizar_telefone('+238 9912345') === '+2389912345');

echo "\n== Validação de NIF (9 dígitos) ==\n";
kg_teste('aceita 123456789', kg_validar_nif('123456789'));
kg_teste('rejeita 8 dígitos', !kg_validar_nif('12345678'));
kg_teste('rejeita com letras', !kg_validar_nif('12345678A'));

echo "\n== Validação de senha (min 8) ==\n";
kg_teste('aceita 8 caracteres', kg_validar_senha('12345678'));
kg_teste('rejeita 7 caracteres', !kg_validar_senha('1234567'));

echo "\n== Coordenadas dentro de Cabo Verde ==\n";
kg_teste('Praia (14.9177,-23.5092) válida', kg_coordenadas_validas(14.9177, -23.5092));
kg_teste('fora do bbox (0,0) inválida', !kg_coordenadas_validas(0, 0));
kg_teste('limite superior lat 17.2 válido', kg_coordenadas_validas(17.2, -23.5));
kg_teste('acima do limite lat 17.3 inválido', !kg_coordenadas_validas(17.3, -23.5));

echo "\n== Distância Haversine ==\n";
$distIgual = kg_distancia_metros(14.9177, -23.5092, 14.9177, -23.5092);
kg_teste('distância entre ponto e ele mesmo é ~0m', abs($distIgual) < 0.01);
$dist1grauLat = kg_distancia_metros(0, 0, 1, 0);
kg_teste('1 grau de latitude é ~111km', abs($dist1grauLat - 111195) < 500);

echo "\n== Layout de assentos (14 lugares: fila 0 = 2, filas 1-4 = 3) ==\n";
$layout = kg_layout_assentos();
kg_teste('total de 14 lugares', count($layout) === 14);
$fila0 = array_filter($layout, fn($l) => $l['fila'] === 0);
kg_teste('fila 0 tem 2 lugares', count($fila0) === 2);
for ($f = 1; $f <= 4; $f++) {
    $filaN = array_filter($layout, fn($l) => $l['fila'] === $f);
    kg_teste("fila {$f} tem 3 lugares", count($filaN) === 3);
}
kg_teste('numeração 1..14 sem repetição', array_column($layout, 'numero') === range(1, 14));

echo "\n== Motor de preços (exemplo da secção 12.3) ==\n";
require_once __DIR__ . '/../includes/pricing.php';
$precosKm = ['urbana' => 5.0, 'intermunicipal' => 10.0];

// Passageiro 1: desce aos 5km, todos dentro da zona urbana -> 5 x 5 = 25 CVE
$preco1 = kg_calcular_preco_proporcional(5, 0, $precosKm);
kg_teste('passageiro a 5km urbanos = 25 CVE', abs($preco1 - 25.0) < 0.001);

// Passageiro 2: 10km urbanos + 15km intermunicipais -> 50 + 150 = 200 CVE
$preco2 = kg_calcular_preco_proporcional(10, 15, $precosKm);
kg_teste('passageiro a 10km urbanos + 15km intermunicipais = 200 CVE', abs($preco2 - 200.0) < 0.001);

echo "\n== Limites de preço (valor mínimo/máximo) ==\n";
$configPrecos = ['valor_minimo' => 100.0, 'valor_maximo' => 5000.0, 'taxa_operacao_rota' => 50.0];
kg_teste('abaixo do mínimo sobe para 100', kg_aplicar_limites_preco(30.0, $configPrecos) === 100.0);
kg_teste('acima do máximo desce para 5000', kg_aplicar_limites_preco(9000.0, $configPrecos) === 5000.0);
kg_teste('dentro dos limites mantém-se', abs(kg_aplicar_limites_preco(300.0, $configPrecos) - 300.0) < 0.001);

echo "\n== Segmentação urbano/intermunicipal por distância (sem BD) ==\n";
// Rota igual à do exemplo da secção 12.1: sai da cidade de origem aos 5km,
// entra na cidade de destino aos 25km, rota total 30km.
$segmentos = ['saida_km' => 5.0, 'entrada_km' => 25.0];
[$u, $i] = kg_zonas_ate_distancia($segmentos, 3.0);
kg_teste('descida antes do limite da cidade (3km) é 100% urbana', $u === 3.0 && $i === 0.0);
[$u, $i] = kg_zonas_ate_distancia($segmentos, 15.0);
kg_teste('descida a meio do troço intermunicipal (15km) = 5 urbano + 10 intermunicipal', $u === 5.0 && $i === 10.0);
[$u, $i] = kg_zonas_ate_distancia($segmentos, 30.0);
kg_teste('rota completa (30km) = 5+5 urbano + 20 intermunicipal', abs($u - 10.0) < 0.001 && abs($i - 20.0) < 0.001);

echo "\n== Interseção reta/círculo (projeção plana em km) ==\n";
$o = kg_latlng_para_xy_km(14.9177, -23.5092, 15.0);
$d = kg_latlng_para_xy_km(15.2785, -23.7519, 15.0);
$raizes = kg_raizes_intersecao_circulo($o, $d, $o, 5.0);
kg_teste('círculo centrado na origem intersecta a reta em t=0 (raiz negativa e positiva)', $raizes !== null && $raizes[0] < 0 && $raizes[1] > 0);

echo "\n===================================\n";
echo "Total: {$total} | Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
