<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Metrics;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Carbon\Carbon;

/**
 * SREMetricsCalculator - Cálculo de métricas SRE (SLI, SLO, SLA, Error Budget)
 *
 * Responsabilidade: Calcular métricas de confiabilidade de serviço
 * - SLI: Service Level Indicator (métrica observada sem meta)
 * - SLO: Service Level Objective (meta operacional interna)
 * - SLA: Service Level Agreement (compromisso contratual)
 * - Error Budget: Margem de erro baseada no SLA
 *
 * Lógica de negócio pura (Domain Layer)
 */
class SREMetricsCalculator
{
    private ?ALBLogDownloaderInterface $alb_downloader = null;

    /**
     * Injeta opcionalmente o downloader de logs (para uso em Controllers)
     */
    public function setALBDownloader(ALBLogDownloaderInterface $downloader): self
    {
        $this->alb_downloader = $downloader;
        return $this;
    }

    /**
     * Calcula métricas SRE para um serviço (API ou UI)
     *
     * @param  int  $total_requests  Total de requisições no período
     * @param  int  $total_5xx  Total de erros 5xx no período
     * @param  float  $slo_target  Meta SLO (padrão de config ou 98.5%)
     * @param  float  $sla_target  Meta SLA (padrão de config ou 95%)
     * @return array Métricas SRE calculadas
     */
    public function calculateForService(
        int $total_requests,
        int $total_5xx,
        ?float $slo_target = null,
        ?float $sla_target = null
    ): array {
        // Use config defaults if not provided
        $slo_target ??= config('insights.sre_targets.API.slo', 98.5);
        $sla_target ??= config('insights.sre_targets.API.sla', 95.0);
        // Validação básica
        if ($total_requests < 0 || $total_5xx < 0) {
            return $this->emptyMetrics($slo_target, $sla_target);
        }

        if ($total_requests === 0) {
            return $this->emptyMetrics($slo_target, $sla_target);
        }

        // SLI: Métrica observada (fato)
        // Fórmula: SLI = 1 - (total_5xx / total_requests)
        $sli = 1 - ($total_5xx / $total_requests);
        $sli_percent = round($sli * 100, 4);

        // Error Budget: Baseado no SLA
        $error_budget_total = 1 - ($sla_target / 100);
        $error_budget_used = $total_5xx / $total_requests;
        $error_budget_remaining = $error_budget_total - $error_budget_used;

        // Conversão para percentuais
        $error_budget_total_percent = round($error_budget_total * 100, 4);
        $error_budget_used_percent = round($error_budget_used * 100, 4);
        $error_budget_remaining_percent = round($error_budget_remaining * 100, 4);

        // Status operacional
        $slo_breached = $sli_percent < $slo_target;
        $sla_at_risk = $error_budget_remaining < 0;

        return [
            // Dados brutos
            'raw' => [
                'total_requests' => $total_requests,
                'total_5xx' => $total_5xx,
            ],

            // SLI (fato observado)
            'sli' => [
                'value' => $sli_percent,
                'unit' => '%',
                'description' => 'Service Level Indicator - métrica observada',
            ],

            // SLO (meta interna)
            'slo' => [
                'target' => $slo_target,
                'unit' => '%',
                'breached' => $slo_breached,
                'description' => 'Service Level Objective - meta operacional interna',
            ],

            // SLA (contrato)
            'sla' => [
                'target' => $sla_target,
                'unit' => '%',
                'at_risk' => $sla_at_risk,
                'description' => 'Service Level Agreement - compromisso contratual',
            ],

            // Error Budget
            'error_budget' => [
                'total' => $error_budget_total_percent,
                'used' => $error_budget_used_percent,
                'remaining' => $error_budget_remaining_percent,
                'unit' => '%',
                'depleted' => $error_budget_remaining < 0,
                'description' => 'Margem de erro baseada no SLA',
            ],

            // Status geral
            'status' => [
                'operational' => ! $slo_breached,
                'slo_violation' => $slo_breached,
                'sla_risk' => $sla_at_risk,
                'healthy' => ! $slo_breached && ! $sla_at_risk,
            ],
        ];
    }

    /**
     * Calcula métricas SRE para múltiplos serviços
     *
     * @param  array  $services  Mapa de serviços ['API' => [...], 'UI' => [...]]
     * @param  float  $slo_target  Meta SLO comum (opcional)
     * @param  float  $sla_target  Meta SLA comum (opcional)
     * @return array Métricas por serviço
     */
    public function calculateForMultipleServices(
        array $services,
        float $slo_target = self::DEFAULT_SLO,
        float $sla_target = self::DEFAULT_SLA
    ): array {
        $results = [];

        foreach ($services as $service_name => $data) {
            $total_requests = $data['total_requests'] ?? 0;
            $total_5xx = $data['total_5xx'] ?? $data['errors_5xx'] ?? 0;

            $results[$service_name] = $this->calculateForService(
                $total_requests,
                $total_5xx,
                $slo_target,
                $sla_target
            );
        }

        return $results;
    }

    /**
     * Calcula métricas SRE usando logs contínuos (nova interface)
     * 
     * Esta é a forma RECOMENDADA: busca logs agregados diariamente
     * que cobrem todo o mês, não apenas o período de incidentes.
     * 
     * @param string $month Formato 'Y-m' (ex: '2026-02')
     * @param float $slo_target Meta SLO
     * @param float $sla_target Meta SLA
     * @return array Métricas por serviço
     */
    public function calculateMonthlyFromContinuousLogs(
        string $month,
        float $slo_target = self::DEFAULT_SLO,
        float $sla_target = self::DEFAULT_SLA
    ): array {
        if (!$this->alb_downloader) {
            throw new \RuntimeException('ALB Downloader not injected. Use setALBDownloader() first.');
        }

        // Obter agregação mensal de logs contínuos
        $logs = $this->alb_downloader->downloadForMonth($month);

        // Calcular métricas por serviço
        $results = [];
        foreach (['API', 'UI'] as $service) {
            $total_requests = $logs['by_request_type'][$service]['total_requests'] ?? 0;
            $total_5xx = $logs['by_request_type'][$service]['errors_5xx'] ?? 0;

            $results[$service] = $this->calculateForService(
                $total_requests,
                $total_5xx,
                $slo_target,
                $sla_target
            );
        }

        return [
            'services' => $results,
            'window' => [
                'start' => $logs['period']['start'] ?? null,
                'end' => $logs['period']['end'] ?? null,
                'type' => 'monthly_cumulative',
            ],
            'calculated_at' => Carbon::now()->toIso8601String(),
            'source' => 'continuous_alb_logs',
        ];
    }

    /**
     * Calcula métricas SRE acumuladas para o mês atual
     *
     * @param  array  $logs  Logs ALB parseados
     * @param  float  $slo_target  Meta SLO
     * @param  float  $sla_target  Meta SLA
     * @return array Métricas por serviço (API, UI)
     */
    public function calculateMonthlyFromLogs(
        array $logs,
        float $slo_target = self::DEFAULT_SLO,
        float $sla_target = self::DEFAULT_SLA
    ): array {
        // Agregar por serviço
        $services = ['API' => [], 'UI' => []];
        $aggregates = [
            'API' => ['total' => 0, '5xx' => 0],
            'UI' => ['total' => 0, '5xx' => 0],
        ];

        $month_start = Carbon::now()->startOfMonth();

        foreach ($logs as $log) {
            // Filtrar apenas produção e mês atual
            $environment = $log['environment'] ?? 'production';
            if ($environment !== 'production') {
                continue;
            }

            $timestamp = isset($log['timestamp']) ? Carbon::parse($log['timestamp']) : null;
            if (! $timestamp || $timestamp->lt($month_start)) {
                continue;
            }

            // Classificar serviço
            $path = $log['path'] ?? '';
            $service = $this->resolveService($path);

            if (! isset($aggregates[$service])) {
                continue;
            }

            $status = $log['target_status_code'] ?? $log['status_code'] ?? 0;

            $aggregates[$service]['total']++;

            if ($status >= 500 && $status < 600) {
                $aggregates[$service]['5xx']++;
            }
        }

        // Calcular métricas para cada serviço
        $results = [];
        foreach (['API', 'UI'] as $service) {
            $results[$service] = $this->calculateForService(
                $aggregates[$service]['total'],
                $aggregates[$service]['5xx'],
                $slo_target,
                $sla_target
            );
        }

        return [
            'services' => $results,
            'window' => [
                'start' => $month_start->toIso8601String(),
                'end' => Carbon::now()->toIso8601String(),
                'type' => 'monthly_cumulative',
            ],
            'calculated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Resolve o tipo de serviço baseado no path
     *
     * @param  string  $path
     * @return string 'API' ou 'UI'
     */
    private function resolveService(string $path): string
    {
        // API: paths que começam com /api/
        if (str_starts_with($path, '/api/')) {
            return 'API';
        }

        // Assets: ignorar (não contam para SLI)
        if (preg_match('/\.(js|css|png|jpg|svg|woff|woff2|ttf|ico)$/i', $path)) {
            return 'ASSETS';
        }

        // UI: tudo que não é API nem assets
        return 'UI';
    }

    /**
     * Retorna estrutura vazia de métricas
     *
     * @param  float  $slo_target
     * @param  float  $sla_target
     * @return array
     */
    private function emptyMetrics(float $slo_target, float $sla_target): array
    {
        return [
            'raw' => [
                'total_requests' => 0,
                'total_5xx' => 0,
            ],
            'sli' => [
                'value' => 100.0,
                'unit' => '%',
                'description' => 'Service Level Indicator - métrica observada',
            ],
            'slo' => [
                'target' => $slo_target,
                'unit' => '%',
                'breached' => false,
                'description' => 'Service Level Objective - meta operacional interna',
            ],
            'sla' => [
                'target' => $sla_target,
                'unit' => '%',
                'at_risk' => false,
                'description' => 'Service Level Agreement - compromisso contratual',
            ],
            'error_budget' => [
                'total' => round((1 - ($sla_target / 100)) * 100, 4),
                'used' => 0.0,
                'remaining' => round((1 - ($sla_target / 100)) * 100, 4),
                'unit' => '%',
                'depleted' => false,
                'description' => 'Margem de erro baseada no SLA',
            ],
            'status' => [
                'operational' => true,
                'slo_violation' => false,
                'sla_risk' => false,
                'healthy' => true,
            ],
        ];
    }
}
