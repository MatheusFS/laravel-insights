<?php

namespace MatheusFS\Laravel\Insights\Support;

/**
 * LargestRemainderDistributor - Distribuição exata usando método Hamilton/Hare
 * 
 * Resolve problema de perda de valores por arredondamento em distribuição proporcional.
 * 
 * Exemplo de uso:
 * ```php
 * $distributor = new LargestRemainderDistributor();
 * 
 * // Distribuir 1078 erros em 2 dias (proporções 31.7% e 68.2%)
 * $result = $distributor->distribute(
 *     total: 1078,
 *     proportions: ['day1' => 0.317, 'day2' => 0.682]
 * );
 * // Resultado: ['day1' => 342, 'day2' => 736]  (soma = 1078 EXATO)
 * ```
 * 
 * Algoritmo (Largest Remainder Method):
 * 1. Calcula valores exatos com proporções (ex: 341.47, 735.28)
 * 2. Usa floor() para parte inteira (341, 735 = 1076)
 * 3. Calcula diferença (1078 - 1076 = 2 unidades perdidas)
 * 4. Se diferença > número de chaves, distribui base + extras
 * 5. Distribui extras para chaves com maiores restos decimais
 * 
 * Garantia: soma(resultado) === total (sem perda por arredondamento)
 * 
 * @see https://en.wikipedia.org/wiki/Largest_remainder_method
 */
class LargestRemainderDistributor
{
    /**
     * Distribui um total exatamente entre múltiplos valores proporcionalmente
     * 
     * @param int $total Total a ser distribuído
     * @param array<string, float> $proportions Array de proporções [key => proportion]
     *                                           Exemplo: ['day1' => 0.317, 'day2' => 0.682]
     *                                           DEVE somar ~1.0 (tolerância de 0.01)
     * @return array<string, int> Array [key => valor distribuído]
     * @throws \InvalidArgumentException Se proporções não somam ~1.0
     */
    public function distribute(int $total, array $proportions): array
    {
        // Validar que proporções somam ~1.0
        $sum_proportions = array_sum($proportions);
        if (abs($sum_proportions - 1.0) > 0.01) {
            throw new \InvalidArgumentException(
                "Proportions must sum to 1.0 (got {$sum_proportions}). " .
                "Example: ['day1' => 0.317, 'day2' => 0.683]"
            );
        }

        $result = [];
        $remainders = [];
        $keys = array_keys($proportions);
        
        // Passo 1 e 2: Calcular valores exatos e floors
        foreach ($keys as $key) {
            $exact_value = $total * $proportions[$key];
            $int_value = (int) floor($exact_value);
            $decimal_part = $exact_value - $int_value;
            
            $result[$key] = $int_value;
            $remainders[$key] = $decimal_part;
        }
        
        // Passo 3: Calcular quantas unidades faltam
        $sum_ints = array_sum($result);
        $difference = $total - $sum_ints;
        
        // Passo 4: Distribuir a diferença usando Largest Remainder
        if ($difference > 0) {
            // Ordenar por maior decimal
            arsort($remainders);
            
            // Quando diferença > número de chaves, distribuir múltiplas unidades
            $num_keys = count($remainders);
            $units_per_key = (int) floor($difference / $num_keys);
            $extra_units = $difference % $num_keys;
            
            // Adicionar unidades base para todas as chaves (se houver)
            if ($units_per_key > 0) {
                foreach ($result as $key => $value) {
                    $result[$key] += $units_per_key;
                }
            }
            
            // Adicionar unidades extras para chaves com maiores restos
            $distributed = 0;
            foreach ($remainders as $key => $decimal) {
                if ($distributed >= $extra_units) {
                    break;
                }
                $result[$key]++;
                $distributed++;
            }
        }
        
        // Validação final: garantir que soma é exata
        $final_sum = array_sum($result);
        if ($final_sum !== $total) {
            throw new \RuntimeException(
                "Largest Remainder algorithm failed: expected {$total}, got {$final_sum}"
            );
        }
        
        return $result;
    }

    /**
     * Distribui com chaves ordenadas (preserva ordem de entrada)
     * 
     * Útil quando a ordem das chaves importa (ex: dias cronológicos)
     * 
     * @param int $total Total a ser distribuído
     * @param array<string, float> $proportions Array ordenado [key => proportion]
     * @return array<string, int> Array [key => valor] na mesma ordem
     */
    public function distributeOrdered(int $total, array $proportions): array
    {
        $keys = array_keys($proportions);
        $result = $this->distribute($total, $proportions);
        
        // Re-ordenar resultado conforme entrada
        $ordered_result = [];
        foreach ($keys as $key) {
            $ordered_result[$key] = $result[$key];
        }
        
        return $ordered_result;
    }

    /**
     * Distribui entre múltiplos totais simultaneamente
     * 
     * Útil para distribuir várias métricas (requests, errors, etc) de uma vez
     * 
     * Exemplo:
     * ```php
     * $result = $distributor->distributeBatch(
     *     totals: [
     *         'total_requests' => 182108,
     *         'errors_5xx' => 1078,
     *     ],
     *     proportions: ['day1' => 0.317, 'day2' => 0.682]
     * );
     * // Resultado:
     * // [
     * //   'day1' => ['total_requests' => 57790, 'errors_5xx' => 342],
     * //   'day2' => ['total_requests' => 124318, 'errors_5xx' => 736],
     * // ]
     * ```
     * 
     * @param array<string, int> $totals Array de totais [metric => total]
     * @param array<string, float> $proportions Array de proporções [key => proportion]
     * @return array<string, array<string, int>> Array [key => [metric => valor]]
     */
    public function distributeBatch(array $totals, array $proportions): array
    {
        $result = [];
        $keys = array_keys($proportions);
        
        // Inicializar estrutura
        foreach ($keys as $key) {
            $result[$key] = [];
        }
        
        // Distribuir cada métrica
        foreach ($totals as $metric => $total) {
            $distribution = $this->distribute($total, $proportions);
            
            foreach ($distribution as $key => $value) {
                $result[$key][$metric] = $value;
            }
        }
        
        return $result;
    }
}
