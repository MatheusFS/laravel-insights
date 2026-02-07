<?php

namespace MatheusFS\Laravel\Insights\Services;

use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use MatheusFS\Laravel\Insights\Services\Domain\Traffic\TrafficAnalyzerService;
use MatheusFS\Laravel\Insights\Services\Domain\Incident\IncidentMetricsCalculator;
use MatheusFS\Laravel\Insights\Services\Domain\Security\WAFRuleGeneratorService;
use MatheusFS\Laravel\Insights\Models\User\Pageview;
use MatheusFS\Laravel\Insights\Models\User\Login;
use MatheusFS\Laravel\Insights\Models\User\AuthenticatedRequest;

/**
 * IncidentCorrelationService - Facade para análise de incidentes
 *
 * Responsabilidade: Orquestrar domain services para análise completa de incidentes
 * Entry point para IncidentAnalysisController
 */
class IncidentCorrelationService
{
    public function __construct(
        private LogParserService $logParser,
        private TrafficAnalyzerService $trafficAnalyzer,
        private IncidentMetricsCalculator $metricsCalculator,
        private WAFRuleGeneratorService $wafRuleGenerator
    ) {}

    /**
     * 1. ANALYZE LOGS - Classifica IPs por comportamento
     *
     * @param  array  $logs  Array de linhas de log bruto
     * @param  array  $filters  Filtros opcionais (ex: apenas API requests)
     * @return array IPs classificados (legitimate, suspicious, malicious)
     */
    public function analyzeLogs(array $logs, array $filters = []): array
    {
        // Parse logs brutos
        $records = $this->logParser->parseLogLines($logs);

        if (empty($records)) {
            return [
                'legitimate' => [],
                'suspicious' => [],
                'malicious' => [],
                'message' => 'No valid log lines parsed',
            ];
        }

        // Aplicar filtros se fornecidos
        if (isset($filters['request_type'])) {
            $records = array_filter($records, fn ($r) => $r['request_type'] === $filters['request_type']);
        }

        // Analisar por tipo de request (API, UI, ASSETS, BOT)
        $byRequestType = $this->trafficAnalyzer->analyzeByRequestType($records);

        // Classificar IPs por comportamento
        $classified = $this->trafficAnalyzer->classifyIpsByBehavior($records);

        return [
            'classified' => $classified,
            'by_request_type' => $byRequestType,
            'total_records' => count($records),
            'total_ips' => count($classified['legitimate']) + count($classified['suspicious']) + count($classified['malicious']),
        ];
    }

    /**
     * 2. CORRELATE - Mapeia IPs → Usuários
     *
     * @param  array  $legitimateIps  IPs classificados como legítimos
     * @param  array  $timeWindow  ['start' => ISO 8601, 'end' => ISO 8601]
     * @param  int  $organizationId  ID da organização (opcional)
     * @return array Usuários afetados
     */
    public function correlateAffectedUsers(
        array $legitimateIps,
        array $timeWindow,
        ?int $organizationId = null
    ): array {
        $startTime = $timeWindow['start'];
        $endTime = $timeWindow['end'];

        // NOTA: Não filtramos por IP porque IPs do ALB (load balancer) são diferentes
        // dos IPs registrados em pageviews/logins (podem vir de proxy/CDN).
        // Estratégia: buscar TODOS os usuários com atividade no período.

        // Buscar em user_pageviews (usa created_at e ip_address padrão)
        $pageviewsQuery = Pageview::query()
            ->whereBetween('created_at', [$startTime, $endTime])
            ->with('user:id,name,email');

        if ($organizationId) {
            $pageviewsQuery->whereHas('user', fn ($q) => $q->where('organization_id', $organizationId));
        }

        $pageviews = $pageviewsQuery->get();

        // Buscar em user_logins (usa created_at padrão Laravel)
        $loginsQuery = Login::query()
            ->whereBetween('created_at', [$startTime, $endTime])
            ->with('user:id,name,email');

        if ($organizationId) {
            $loginsQuery->whereHas('user', fn ($q) => $q->where('organization_id', $organizationId));
        }

        $logins = $loginsQuery->get();

        // Buscar em user_requests (usa created_at padrão Laravel)
        $userRequestsQuery = AuthenticatedRequest::query()
            ->whereBetween('created_at', [$startTime, $endTime])
            ->with('user:id,name,email');

        if ($organizationId) {
            $userRequestsQuery->whereHas('user', fn ($q) => $q->where('organization_id', $organizationId));
        }

        $userRequests = $userRequestsQuery->get();

        // Mapear usuários únicos
        $affectedUsers = [];

        foreach ($pageviews as $pv) {
            if (! $pv->user) {
                continue;
            }

            $userId = $pv->user->id;
            if (! isset($affectedUsers[$userId])) {
                $affectedUsers[$userId] = [
                    'ip' => $pv->ip_address,
                    'user_id' => $userId,
                    'name' => $pv->user->name,
                    'email' => $pv->user->email,
                    'source' => 'pageviews',
                    'requests' => 0,
                    'errors' => 0,
                ];
            }
            $affectedUsers[$userId]['requests']++;
        }

        foreach ($logins as $login) {
            if (! $login->user) {
                continue;
            }

            $userId = $login->user->id;
            if (! isset($affectedUsers[$userId])) {
                $affectedUsers[$userId] = [
                    'ip' => $login->ip_address,
                    'user_id' => $userId,
                    'name' => $login->user->name,
                    'email' => $login->user->email,
                    'source' => 'logins',
                    'requests' => 0,
                    'errors' => 0,
                ];
            }
            $affectedUsers[$userId]['requests']++;
            if (! $login->success) {
                $affectedUsers[$userId]['errors']++;
            }
        }

        foreach ($userRequests as $req) {
            if (! $req->user) {
                continue;
            }

            $userId = $req->user->id;
            if (! isset($affectedUsers[$userId])) {
                $affectedUsers[$userId] = [
                    'ip' => $req->ip,
                    'user_id' => $userId,
                    'name' => $req->user->name,
                    'email' => $req->user->email,
                    'source' => 'user_requests',
                    'requests' => 0,
                    'errors' => 0,
                ];
            }
            $affectedUsers[$userId]['requests']++;
            if ($req->status_code >= 400) {
                $affectedUsers[$userId]['errors']++;
            }
        }

        $criticalThreshold = (float)config('insights.user_impact.critical_error_rate_min', 10.0);
        $criticalUsers = [];

        foreach ($affectedUsers as &$user) {
            $requests = $user['requests'] ?? 0;
            $errors = $user['errors'] ?? 0;
            $errorRate = $requests > 0 ? ($errors / $requests) * 100 : 0;

            $user['error_rate'] = round($errorRate, 2);
            $user['is_critical'] = $user['error_rate'] > $criticalThreshold;

            if ($user['is_critical']) {
                $criticalUsers[] = $user;
            }
        }
        unset($user);

        return [
            'total' => count($affectedUsers),
            'users' => array_values($affectedUsers),
            'critical_affected_users' => $criticalUsers,
            'sources' => [
                'pageviews' => $pageviews->count(),
                'logins' => $logins->count(),
                'user_requests' => $userRequests->count(),
            ],
        ];
    }

    /**
     * 3. METRICS - Calcula impacto
     *
     * @param  array  $affectedUsers  Usuários afetados (retorno de correlateAffectedUsers)
     * @param  array  $timeWindow  ['start' => ISO 8601, 'end' => ISO 8601]
     * @return array Métricas de impacto
     */
    public function calculateImpactMetrics(array $affectedUsers, array $timeWindow): array
    {
        $totalUsers = count($affectedUsers);
        $totalRequests = array_sum(array_column($affectedUsers, 'requests'));
        $totalErrors = array_sum(array_column($affectedUsers, 'errors'));

        $errorRate = $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;

        return [
            'users_affected' => $totalUsers,
            'requests_total' => $totalRequests,
            'requests_failed' => $totalErrors,
            'error_rate' => round($errorRate, 2),
            'time_window' => $timeWindow,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 4. WAF RULES - Gera regras de bloqueio
     *
     * @param  array  $maliciousIps  IPs classificados como maliciosos
     * @param  string  $incidentId  ID do incidente
     * @return array Regras WAF geradas
     */
    public function generateWAFRules(array $maliciousIps, string $incidentId): array
    {
        if (empty($maliciousIps)) {
            return [
                'rules' => [],
                'summary' => 'No malicious IPs to block',
            ];
        }

        $classified = [
            'malicious' => $maliciousIps,
            'suspicious' => [],
            'legitimate' => [],
        ];

        $ruleset = $this->wafRuleGenerator->generateCompleteRuleset($classified, $incidentId);

        return [
            'rules' => $ruleset,
            'summary' => [
                'blocklist_ips' => count($ruleset['blocklist']['ips']),
                'watchlist_ips' => count($ruleset['watchlist']['ips']),
                'allowlist_ips' => count($ruleset['allowlist']['ips']),
            ],
        ];
    }

    /**
     * 5. ANOMALIES - Detecta padrões estranhos
     *
     * @param  int  $organizationId  ID da organização
     * @param  int  $daysLookback  Dias para buscar no histórico
     * @return array Anomalias detectadas
     */
    public function detectAnomalies(int $organizationId, int $daysLookback = 7): array
    {
        // TODO: Implementar detecção de anomalias
        // - Novos IPs
        // - Mudanças de geolocalização
        // - Spikes de failed logins
        // - Mudanças de User-Agent

        return [
            'new_ips' => [],
            'geolocation_changes' => [],
            'failed_logins_spike' => false,
            'message' => 'Anomaly detection not yet implemented',
        ];
    }
}
