<?php

namespace MatheusFS\Laravel\Insights\Services\Domain;

use Carbon\Carbon;

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
    /**
     * Detectores de tipo de requisição por padrão de URL/User-Agent
     */
    private array $service_patterns = [
        'API' => [
            'path' => ['^/api/', '^/v\d+/'],
            'user_agent' => ['axios', 'fetch', 'curl'],
        ],
        'UI' => [
            'path' => ['^/$', '^/briefing', '^/project', '^/dashboard', '^/reliability'],
            'user_agent' => ['Mozilla', 'Chrome', 'Safari', 'Firefox'],
        ],
        'BOT' => [
            'user_agent' => ['bot', 'crawler', 'spider', 'scraper', 'googlebot', 'bingbot'],
        ],
        'ASSETS' => [
            'path' => ['\.(js|css|png|jpg|gif|ico|svg|woff|woff2|ttf|eot)$'],
        ],
    ];

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
        $path = $log['path'] ?? '';
        $user_agent = $log['user_agent'] ?? '';

        // Verificar ASSETS por extensão
        if ($this->matchesPattern($path, $this->service_patterns['ASSETS']['path'] ?? [])) {
            return 'ASSETS';
        }

        // Verificar BOT por user-agent
        if ($this->matchesPattern($user_agent, $this->service_patterns['BOT']['user_agent'] ?? [])) {
            return 'BOT';
        }

        // Verificar API por path
        if ($this->matchesPattern($path, $this->service_patterns['API']['path'] ?? [])) {
            return 'API';
        }

        // Verificar API por user-agent (requisições de client JS)
        if ($this->matchesPattern($user_agent, $this->service_patterns['API']['user_agent'] ?? [])) {
            if ($this->matchesPattern($path, $this->service_patterns['API']['path'] ?? [])) {
                return 'API';
            }
        }

        // Default: UI
        return 'UI';
    }

    /**
     * Verifica se uma string corresponde a algum padrão regex
     * 
     * @param string $subject String a testar
     * @param array $patterns Padrões regex
     * @return bool True se houver match
     */
    private function matchesPattern(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Usa delimitador # ao invés de / para evitar conflito com / em paths
            if (preg_match("#$pattern#i", $subject)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Define padrões de detecção customizados
     * 
     * Útil para override em testes ou configuração via config file
     * 
     * @param array $patterns Padrões customizados
     * @return self
     */
    public function setPatterns(array $patterns): self
    {
        $this->service_patterns = array_merge_recursive($this->service_patterns, $patterns);
        return $this;
    }
}
