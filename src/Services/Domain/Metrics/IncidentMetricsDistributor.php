<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Metrics;

use Carbon\Carbon;
use MatheusFS\Laravel\Insights\Support\LargestRemainderDistributor;

/**
 * IncidentMetricsDistributor - Distribui métricas de incidente por período temporal
 * 
 * Usa Largest Remainder Method para garantir que a soma das métricas distribuídas
 * seja EXATAMENTE igual ao total do incidente (sem perda por arredondamento).
 * 
 * Caso de uso típico:
 * - Incidente dura 2+ dias
 * - Precisa dividir erros/requests proporcionalmente por dia
 * - Quer garantir que soma dos dias = total do incidente
 * 
 * Exemplo:
 * ```php
 * $distributor = new IncidentMetricsDistributor();
 * 
 * $result = $distributor->distributeByDuration(
 *     totals: ['errors_5xx' => 1078, 'total_requests' => 182108],
 *     started_at: '2026-02-02T22:25:00',
 *     restored_at: '2026-02-03T12:50:00',
 *     timezone: 'America/Sao_Paulo'
 * );
 * 
 * // Resultado:
 * // [
 * //   '2026-02-02' => ['errors_5xx' => 342, 'total_requests' => 57790],
 * //   '2026-02-03' => ['errors_5xx' => 736, 'total_requests' => 124318],
 * // ]
 * // ✅ Soma exata garantida!
 * ```
 */
class IncidentMetricsDistributor
{
    private LargestRemainderDistributor $distributor;

    public function __construct()
    {
        $this->distributor = new LargestRemainderDistributor();
    }

    /**
     * Distribui métricas de incidente proporcionalmente por dia baseado em duração
     * 
     * @param array<string, int> $totals Totais do incidente [metric => total]
     * @param string $started_at Timestamp ISO 8601 do início
     * @param string $restored_at Timestamp ISO 8601 da restauração
     * @param string $timezone Timezone (ex: 'America/Sao_Paulo')
     * @return array<string, array<string, int>> [date => [metric => value]]
     * 
     * @throws \InvalidArgumentException Se restored_at <= started_at
     */
    public function distributeByDuration(
        array $totals,
        string $started_at,
        string $restored_at,
        string $timezone = 'UTC'
    ): array {
        $start = Carbon::parse($started_at)->setTimezone($timezone);
        $end = Carbon::parse($restored_at)->setTimezone($timezone);

        if ($end <= $start) {
            throw new \InvalidArgumentException(
                "restored_at must be after started_at (got start={$started_at}, end={$restored_at})"
            );
        }

        // Calcular proporções por dia baseado em minutos de incidente em cada dia
        $proportions = $this->calculateDailyProportions($start, $end);

        // Distribuir métricas usando Largest Remainder
        return $this->distributor->distributeBatch($totals, $proportions);
    }

    /**
     * Calcula proporções de duração do incidente por dia
     * 
     * Exemplo: Incidente 22:25 dia 1 até 12:50 dia 2
     * - Dia 1: 22:25 até 23:59 = 94 minutos = 31.7%
     * - Dia 2: 00:00 até 12:50 = 770 minutos = 68.3%
     * 
     * @param Carbon $start Início do incidente
     * @param Carbon $end Fim do incidente
     * @return array<string, float> [date => proportion]
     */
    private function calculateDailyProportions(Carbon $start, Carbon $end): array
    {
        $total_minutes = $end->diffInMinutes($start);

        if ($total_minutes === 0) {
            throw new \InvalidArgumentException('Incident duration is 0 minutes');
        }

        $proportions = [];
        $current = $start->copy();

        while ($current < $end) {
            $date_key = $current->format('Y-m-d');
            $day_end = $current->copy()->endOfDay();

            // Minutos neste dia
            $day_minutes = min(
                $day_end->diffInMinutes($current),
                $end->diffInMinutes($current)
            );

            $proportions[$date_key] = $day_minutes / $total_minutes;

            // Próximo dia
            $current = $current->copy()->addDay()->startOfDay();
        }

        return $proportions;
    }

    /**
     * Distribui métricas com proporções customizadas
     * 
     * Útil quando você já calculou as proporções ou quer distribuir de forma diferente.
     * 
     * @param array<string, int> $totals Totais [metric => total]
     * @param array<string, float> $proportions Proporções [key => proportion]
     * @return array<string, array<string, int>> [key => [metric => value]]
     */
    public function distributeWithProportions(array $totals, array $proportions): array
    {
        return $this->distributor->distributeBatch($totals, $proportions);
    }

    /**
     * Distribui métricas igualmente entre N períodos
     * 
     * Exemplo: Distribuir 1000 erros igualmente entre 3 dias
     * 
     * @param array<string, int> $totals Totais [metric => total]
     * @param array<string> $periods Array de chaves (ex: ['day1', 'day2', 'day3'])
     * @return array<string, array<string, int>> [period => [metric => value]]
     */
    public function distributeEqually(array $totals, array $periods): array
    {
        $num_periods = count($periods);

        if ($num_periods === 0) {
            throw new \InvalidArgumentException('Periods array cannot be empty');
        }

        // Proporções iguais
        $proportion = 1.0 / $num_periods;
        $proportions = array_fill_keys($periods, $proportion);

        return $this->distributor->distributeBatch($totals, $proportions);
    }

    /**
     * Distribui métricas por serviço (API, UI, BOT, etc)
     * 
     * Mantém separação por request_type enquanto distribui por período.
     * 
     * Exemplo de entrada:
     * ```php
     * $metrics_by_service = [
     *     'API' => ['errors_5xx' => 1078, 'total_requests' => 182108],
     *     'UI' => ['errors_5xx' => 562, 'total_requests' => 107565],
     * ];
     * ```
     * 
     * Exemplo de saída:
     * ```php
     * [
     *     '2026-02-02' => [
     *         'API' => ['errors_5xx' => 342, 'total_requests' => 57790],
     *         'UI' => ['errors_5xx' => 178, 'total_requests' => 34135],
     *     ],
     *     '2026-02-03' => [
     *         'API' => ['errors_5xx' => 736, 'total_requests' => 124318],
     *         'UI' => ['errors_5xx' => 384, 'total_requests' => 73430],
     *     ],
     * ]
     * ```
     * 
     * @param array<string, array<string, int>> $metrics_by_service [service => [metric => total]]
     * @param string $started_at Timestamp ISO 8601
     * @param string $restored_at Timestamp ISO 8601
     * @param string $timezone Timezone
     * @return array<string, array<string, array<string, int>>> [date => [service => [metric => value]]]
     */
    public function distributeByServiceAndDuration(
        array $metrics_by_service,
        string $started_at,
        string $restored_at,
        string $timezone = 'UTC'
    ): array {
        $start = Carbon::parse($started_at)->setTimezone($timezone);
        $end = Carbon::parse($restored_at)->setTimezone($timezone);

        // Calcular proporções por dia
        $proportions = $this->calculateDailyProportions($start, $end);

        $result = [];

        // Inicializar estrutura [date => [service => []]]
        foreach (array_keys($proportions) as $date) {
            $result[$date] = [];
            foreach (array_keys($metrics_by_service) as $service) {
                $result[$date][$service] = [];
            }
        }

        // Distribuir cada serviço
        foreach ($metrics_by_service as $service => $totals) {
            $distributed = $this->distributor->distributeBatch($totals, $proportions);

            foreach ($distributed as $date => $metrics) {
                $result[$date][$service] = $metrics;
            }
        }

        return $result;
    }
}
