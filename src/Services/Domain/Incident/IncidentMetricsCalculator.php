<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Incident;

/**
 * IncidentMetricsCalculator - Cálculo de métricas de impacto
 *
 * Responsabilidade: Calcular métricas reais baseadas em logs e janela temporal
 * Lógica de negócio pura
 */
class IncidentMetricsCalculator
{
    /**
     * Calcula métricas de impacto do incidente
     *
     * @param  array  $records  Registros parseados do log
     * @param  string  $startTime  Início do incidente (ISO 8601)
     * @param  string  $endTime  Fim do incidente (ISO 8601)
     * @return array Métricas calculadas
     */
    public function calculateMetrics(array $records, string $startTime, string $endTime): array
    {
        // Filtrar registros pela janela temporal
        $filteredRecords = $this->filterByTimeWindow($records, $startTime, $endTime);

        if (empty($filteredRecords)) {
            return $this->emptyMetrics();
        }

        $totalRequests = count($filteredRecords);
        $errors5xx = 0;
        $errors4xx = 0;
        $errorsElb5xx = 0;
        $latencies = [];
        $healthyTargets = 0;
        $unhealthyTargets = 0;

        foreach ($filteredRecords as $record) {
            $targetStatus = $record['target_status_code'] ?? 0;
            $elbStatus = $record['elb_status_code'] ?? 0;

            // Contar erros
            if ($targetStatus >= 500) {
                $errors5xx++;
            } elseif ($targetStatus >= 400) {
                $errors4xx++;
            }

            if ($elbStatus >= 500) {
                $errorsElb5xx++;
            }

            // Coletar latências (se disponível)
            if (isset($record['target_processing_time']) && $record['target_processing_time'] > 0) {
                $latencies[] = $record['target_processing_time'] * 1000; // converter para ms
            }

            // Health checks (se disponível)
            if (isset($record['target_status'])) {
                if ($record['target_status'] === 'healthy') {
                    $healthyTargets++;
                } else {
                    $unhealthyTargets++;
                }
            }
        }

        return [
            'total_requests' => $totalRequests,
            'errors_5xx' => [
                'count' => $errors5xx,
                'percentage' => round(($errors5xx / $totalRequests) * 100, 2),
            ],
            'errors_4xx' => [
                'count' => $errors4xx,
                'percentage' => round(($errors4xx / $totalRequests) * 100, 2),
            ],
            'errors_elb_5xx' => [
                'count' => $errorsElb5xx,
                'percentage' => round(($errorsElb5xx / $totalRequests) * 100, 2),
            ],
            'latency_p99_ms' => ! empty($latencies) ? $this->calculatePercentile($latencies, 99) : 0,
            'latency_violates_slo' => false, // TODO: comparar com SLO configurado
            'health_healthy_hosts' => $healthyTargets,
            'health_unhealthy_hosts' => $unhealthyTargets,
            'collection_method' => 'ALB Access Logs + CloudWatch',
            'time_window' => [
                'start' => $startTime,
                'end' => $endTime,
            ],
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Filtra registros pela janela temporal
     *
     * @param  array  $records  Registros parseados
     * @param  string  $startTime  Início (ISO 8601)
     * @param  string  $endTime  Fim (ISO 8601)
     * @return array Registros filtrados
     */
    private function filterByTimeWindow(array $records, string $startTime, string $endTime): array
    {
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);

        return array_filter($records, function ($record) use ($startTimestamp, $endTimestamp) {
            if (! isset($record['timestamp'])) {
                return false;
            }

            $recordTimestamp = strtotime($record['timestamp']);

            return $recordTimestamp >= $startTimestamp && $recordTimestamp <= $endTimestamp;
        });
    }

    /**
     * Calcula percentil de um array de valores
     *
     * @param  array  $values  Valores numéricos
     * @param  int  $percentile  Percentil desejado (0-100)
     * @return float Valor do percentil
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $index = (int) ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return round($values[$index], 2);
    }

    /**
     * Retorna estrutura vazia de métricas
     */
    private function emptyMetrics(): array
    {
        return [
            'total_requests' => 0,
            'errors_5xx' => ['count' => 0, 'percentage' => 0.0],
            'errors_4xx' => ['count' => 0, 'percentage' => 0.0],
            'errors_elb_5xx' => ['count' => 0, 'percentage' => 0.0],
            'latency_p99_ms' => 0,
            'latency_violates_slo' => false,
            'health_healthy_hosts' => 0,
            'health_unhealthy_hosts' => 0,
            'collection_method' => 'ALB Access Logs',
        ];
    }
}
