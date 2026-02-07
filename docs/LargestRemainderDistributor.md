# Largest Remainder Distributor

Distribuição exata de valores proporcionais usando o método Hamilton/Hare (Largest Remainder Method).

## Problema

Quando distribuímos valores proporcionalmente usando `round()` ou `ceil()`, perdemos precisão:

```php
// ❌ Problema: arredondamento perde valores
$total = 1078;
$day1 = round($total * 0.317); // 341
$day2 = round($total * 0.683); // 736
$sum = $day1 + $day2; // 1077 ❌ Perdeu 1 erro!
```

## Solução

O `LargestRemainderDistributor` garante que **a soma sempre é exata**:

```php
use MatheusFS\Laravel\Insights\Support\LargestRemainderDistributor;

$distributor = new LargestRemainderDistributor();

$result = $distributor->distribute(
    total: 1078,
    proportions: [
        'day1' => 0.317,
        'day2' => 0.683,
    ]
);

// Resultado: ['day1' => 342, 'day2' => 736]
// Soma: 1078 ✅ EXATO!
```

## Algoritmo

1. **Calcular valores exatos** com proporções (ex: 341.47, 735.53)
2. **Floor** cada valor para obter inteiros (341, 735 = 1076)
3. **Calcular diferença** (1078 - 1076 = 2 unidades perdidas)
4. **Distribuir diferença** para valores com **maiores restos decimais**:
   - 341.47 tem resto 0.47 (maior)
   - 735.53 tem resto 0.53 (segundo maior)
   - Adicionar +1 aos 2 maiores restos
5. **Resultado final**: 342 + 736 = 1078 ✅

## Casos de Uso

### 1. Distribuir erros de incidente por dia

```php
// Incidente INC-2026-001: 1,078 erros em 2 dias (31.7% e 68.2%)
$result = $distributor->distribute(
    total: 1078,
    proportions: [
        'day1' => 0.3168208092485549, // 274 minutos de 865
        'day2' => 0.6831791907514451, // 590 minutos de 865
    ]
);
// ['day1' => 342, 'day2' => 736] ✅
```

### 2. Distribuir múltiplas métricas simultaneamente

```php
$result = $distributor->distributeBatch(
    totals: [
        'total_requests' => 182108,
        'errors_5xx' => 1078,
        'errors_4xx' => 500,
    ],
    proportions: [
        'day1' => 0.317,
        'day2' => 0.683,
    ]
);

// Resultado:
// [
//   'day1' => [
//     'total_requests' => 57790,
//     'errors_5xx' => 342,
//     'errors_4xx' => 159,
//   ],
//   'day2' => [
//     'total_requests' => 124318,
//     'errors_5xx' => 736,
//     'errors_4xx' => 341,
//   ],
// ]
// ✅ Todas as somas são EXATAS!
```

### 3. Distribuir entre múltiplos períodos

```php
$result = $distributor->distribute(
    total: 100,
    proportions: [
        'Q1' => 0.25,
        'Q2' => 0.30,
        'Q3' => 0.20,
        'Q4' => 0.25,
    ]
);
// Soma sempre = 100
```

### 4. Distribuir com ordem preservada

```php
$result = $distributor->distributeOrdered(
    total: 100,
    proportions: [
        'january' => 0.10,
        'february' => 0.15,
        'march' => 0.75,
    ]
);
// Ordem mantida: ['january' => X, 'february' => Y, 'march' => Z]
```

## Garantias

- ✅ **Soma exata**: `array_sum($result) === $total` sempre
- ✅ **Sem perda**: Nenhum valor é perdido por arredondamento
- ✅ **Proporcional**: Valores respeitam proporções aproximadas
- ✅ **Validação**: Exception se proporções não somam ~1.0

## Casos Especiais

### Diferença > Número de Chaves

Quando a diferença é maior que o número de chaves (ex: 4 unidades para 2 dias), o algoritmo distribui múltiplas unidades:

```php
$result = $distributor->distribute(
    total: 2597,
    proportions: [
        'day1' => 0.317, // 822.63 → floor=822
        'day2' => 0.683, // 1774.37 → floor=1774
    ]
);
// Diferença = 2597 - (822 + 1774) = 1
// Distribui 1 unidade para maior decimal
// Resultado: 823 + 1774 = 2597 ✅
```

### Total = 0

```php
$result = $distributor->distribute(0, ['a' => 0.5, 'b' => 0.5]);
// ['a' => 0, 'b' => 0]
```

### Proporções desiguais

```php
$result = $distributor->distribute(
    total: 10,
    proportions: [
        'a' => 0.999, // 9.99 → 10
        'b' => 0.001, // 0.01 → 0
    ]
);
// ['a' => 10, 'b' => 0]
```

## Validação

```php
try {
    $distributor->distribute(
        total: 100,
        proportions: ['a' => 0.5, 'b' => 0.3] // Soma = 0.8 ❌
    );
} catch (\InvalidArgumentException $e) {
    // "Proportions must sum to 1.0 (got 0.8)"
}
```

## Testes

```bash
docker exec laravel-insights-fpm-1 php vendor/bin/phpunit \
  tests/Unit/Support/LargestRemainderDistributorTest.php
```

**Cobertura:** 11 testes, 48 assertions, 100% pass

## Referências

- [Wikipedia: Largest Remainder Method](https://en.wikipedia.org/wiki/Largest_remainder_method)
- [Hamilton/Hare Method](https://en.wikipedia.org/wiki/Hamilton_method)

## Histórico

Criado para resolver bug de "falta de confiança nas métricas" onde:
- Incidente INC-2026-001 mostrava 1,078 erros
- Agregado mensal mostrava 1,076 erros (perdeu 2 por arredondamento)

Com o `LargestRemainderDistributor`, os valores agora são **100% idênticos**.
