<?php

namespace MatheusFS\Laravel\Insights\Services\Domain;

use Carbon\Carbon;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;

/**
 * Analisador de Logs ALB
 * 
 * Extrai e agrega dados de requisições por tipo de serviço:
 * - API (requisições de API)
 * - UI (requisições de frontend/SPA)
 * - BOT (requisições de bots/crawlers)
 * - ASSETS (requisições de assets estáticos)
 * 
 * Conta:
 * - Total de requisições por tipo
 * - Erros 5xx por tipo
 * - Latência média (opcional)
 */
class ALBLogAnalyzer
{
    public function __construct(
        private LogParserService $logParser
    ) {}

    /**
     * Analisa logs ALB e agrega por tipo de requisição
     * 
     * @param array $logs Logs do ALB (estrutura: array de requisições)
     * @param Carbon $date Data de referência
     * @return array Agregação por tipo de serviço
     */
    public function analyze(array $logs, Carbon $date): array
    {
        $aggregate = [
            'by_request_type' => [
                'API' => ['total_requests' => 0, 'errors_5xx' => 0],
                'UI' => ['total_requests' => 0, 'errors_5xx' => 0],
                'BOT' => ['total_requests' => 0, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 0, 'errors_5xx' => 0],
            ],
            'period' => [
                'start' => $date->startOfDay()->toIso8601String(),
                'end' => $date->endOfDay()->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        // Se logs já estão agregados (vindo de ALB análise prévia)
        if (isset($logs['by_request_type'])) {
            return array_merge($aggregate, $logs);
        }

        // Se logs são array de requisições individuais
        if (is_array($logs) && !empty($logs)) {
            foreach ($logs as $log) {
                $this->processLogEntry($log, $aggregate);
            }
        }

        \Log::info("ALBLogAnalyzer result for {$date->format('Y-m-d')}", [
            'input_count' => count($logs),
            'API_count' => $aggregate['by_request_type']['API']['total_requests'],
            'UI_count' => $aggregate['by_request_type']['UI']['total_requests'],
            'BOT_count' => $aggregate['by_request_type']['BOT']['total_requests'],
            'ASSETS_count' => $aggregate['by_request_type']['ASSETS']['total_requests'],
        ]);

        return $aggregate;
    }

    /**
     * Processa uma entrada individual de log
     * 
     * @param array $log Entrada de log ALB
     * @param array &$aggregate Agregação a atualizar
     */
    private function processLogEntry(array $log, array &$aggregate): void
    {
        if ($log['is_staging'] ?? false) {
            return;
        }

        // Detectar tipo de requisição
        $service_type = $this->detectServiceType($log);

        // Contar requisição
        $aggregate['by_request_type'][$service_type]['total_requests']++;

        // Contar erro 5xx
        $status_code = (int)($log['status_code'] ?? 200);
        if ($status_code >= 500 && $status_code < 600) {
            $aggregate['by_request_type'][$service_type]['errors_5xx']++;
        }
    }

    /**
     * Detecta o tipo de serviço baseado em padrões (path, user-agent, etc)
     * 
     * Prioridade:
     * 1. ASSETS (extensão de arquivo)
     * 2. BOT (user-agent)
     * 3. API (padrão de path /api/)
     * 4. UI (default)
     * 
     * @param array $log Entrada de log
     * @return string Tipo detectado (API|UI|BOT|ASSETS)
     */
    private function detectServiceType(array $log): string
    {
        return $this->logParser->classifyRequestType(
            $log['path'] ?? '',
            $log['user_agent'] ?? ''
        );
    }

}
