<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Traffic;

/**
 * TrafficAnalyzerService - Análise de tráfego e erros
 *
 * Responsabilidade: Calcular métricas, identificar padrões, agrupar por tipo
 * Lógica de negócio pura
 */
class TrafficAnalyzerService
{
    /**
     * Analisa registros separados por tipo de request
     *
     * @param  array  $records  Registros parseados do log
     * @return array Métricas agrupadas por tipo (API, UI, ASSETS) + BOT/MALICIOUS
     */
    public function analyzeByRequestType(array $records): array
    {
        $recordsByType = ['API' => [], 'UI' => [], 'ASSETS' => [], 'BOT' => []];
        $errorsByType = [
            'API' => $this->initializeErrorMetrics(),
            'UI' => $this->initializeErrorMetrics(),
            'ASSETS' => $this->initializeErrorMetrics(),
            'BOT' => $this->initializeErrorMetrics(),
        ];

        // Agrupar registros e rastrear erros
        foreach ($records as $record) {
            // Usar request_type já classificado pelo LogParserService
            $type = $record['request_type'] ?? 'UI';

            $recordsByType[$type][] = $record;

            $statusCode = $record['target_status_code'] ?? $record['elb_status_code'];

            if ($statusCode >= 400) {
                $errorsByType[$type]['total']++;
                $errorsByType[$type]['ips'][$record['client_ip']] = true;
                $path = $record['path'] ?? '';
                $errorsByType[$type]['paths'][$path] =
                    ($errorsByType[$type]['paths'][$path] ?? 0) + 1;

                if ($statusCode >= 500) {
                    $errorsByType[$type]['5xx']++;
                } elseif ($statusCode >= 400) {
                    $errorsByType[$type]['4xx']++;
                }
            }
        }

        // Calcular métricas finais
        $results = [];
        foreach (['API', 'UI', 'ASSETS', 'BOT'] as $type) {
            $total = count($recordsByType[$type]);
            $errors = $errorsByType[$type];

            $errorRate = $total > 0 ? ($errors['total'] / $total * 100) : 0;

            $results[$type] = [
                'total_requests' => $total,
                'total_errors' => $errors['total'],
                'errors_5xx' => $errors['5xx'],
                'errors_4xx' => $errors['4xx'],
                'error_rate' => round($errorRate, 2),
                'unique_ips_with_errors' => count($errors['ips']),
                'top_error_paths' => $this->getTopPaths($errors['paths'], 10),
            ];
        }

        return $results;
    }

    /**
     * Identifica IPs com padrões suspeitos
     *
     * @param  array  $records  Registros parseados
     * @return array IPs classificados (malicious, suspicious, legitimate)
     */
    public function classifyIpsByBehavior(array $records): array
    {
        $ipMetrics = [];

        // Calcular métricas por IP
        foreach ($records as $record) {
            $ip = $record['client_ip'];

            if (! isset($ipMetrics[$ip])) {
                $ipMetrics[$ip] = [
                    'total_requests' => 0,
                    'errors' => 0,
                    'errors_5xx' => 0,
                    'paths' => [],
                    'user_agents' => [],
                ];
            }

            $ipMetrics[$ip]['total_requests']++;

            $statusCode = $record['target_status_code'] ?? $record['elb_status_code'];
            if ($statusCode >= 400) {
                $ipMetrics[$ip]['errors']++;
                if ($statusCode >= 500) {
                    $ipMetrics[$ip]['errors_5xx']++;
                }
            }

            $ipMetrics[$ip]['paths'][$record['path']] = true;
            $ipMetrics[$ip]['user_agents'][$record['user_agent']] = true;
        }

        // Classificar IPs
        $classified = [
            'malicious' => [],
            'suspicious' => [],
            'legitimate' => [],
        ];

        foreach ($ipMetrics as $ip => $metrics) {
            $errorRate = $metrics['errors'] / $metrics['total_requests'];
            $uniquePaths = count($metrics['paths']);
            $uniqueUserAgents = count($metrics['user_agents']);

            // Critérios AJUSTADOS - focar em comportamento claramente malicioso
            // IPs de usuários reais podem ter error rates altos durante incidentes!
            if ($errorRate >= 0.95 && $metrics['total_requests'] > 200) {
                // MALICIOSO: Quase 100% de erros + alto volume
                $classified['malicious'][] = [
                    'ip' => $ip,
                    'total_requests' => $metrics['total_requests'],
                    'error_rate' => round($errorRate * 100, 2),
                    'reason' => 'Nearly 100% error rate + very high volume',
                ];
            } elseif ($errorRate >= 0.9 || $uniquePaths > 100) {
                // SUSPEITO: Error rate altíssimo OU path scanning massivo
                $classified['suspicious'][] = [
                    'ip' => $ip,
                    'total_requests' => $metrics['total_requests'],
                    'error_rate' => round($errorRate * 100, 2),
                    'unique_paths' => $uniquePaths,
                    'reason' => 'Very high error rate or excessive path scanning',
                ];
            } else {
                // LEGÍTIMO: Tudo o resto (inclui usuários reais afetados pelo incidente)
                $classified['legitimate'][] = [
                    'ip' => $ip,
                    'total_requests' => $metrics['total_requests'],
                    'error_rate' => round($errorRate * 100, 2),
                ];
            }
        }

        return $classified;
    }

    /**
     * Inicializa estrutura de métricas de erro
     */
    private function initializeErrorMetrics(): array
    {
        return [
            'total' => 0,
            '5xx' => 0,
            '4xx' => 0,
            'ips' => [],
            'paths' => [],
        ];
    }

    /**
     * Retorna top N paths por contagem
     */
    private function getTopPaths(array $paths, int $limit): array
    {
        arsort($paths);

        return array_slice($paths, 0, $limit, true);
    }
}
