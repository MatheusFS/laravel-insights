<?php

namespace MatheusFS\Laravel\Insights\Services\Application;

use Illuminate\Support\Facades\File;
use MatheusFS\Laravel\Insights\Services\IncidentCorrelationService;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use Carbon\Carbon;

/**
 * IncidentAnalysisService - Orquestração de análise de incidentes (Application Layer)
 *
 * Coordena IncidentCorrelationService + S3LogDownloaderService para workflows SRE
 * Gerencia download de logs AWS S3, análise de impacto e correlação de usuários
 */
class IncidentAnalysisService
{
    protected string $incidents_base_path;

    public function __construct(
        private IncidentCorrelationService $correlationService,
        private S3LogDownloaderService $s3Downloader
    ) {
        $this->incidents_base_path = config('insights.incident_correlation.storage_path', storage_path('app/incidents'));
    }

    /**
     * 1. Analisa logs ALB de um incidente
     * 
     * Fluxo:
     * 1. Carrega incidente para obter datas (started_at, restored_at)
     * 2. Baixa logs do S3 (usando S3LogDownloaderService)
     * 3. Lê logs do diretório específico do incidente
     * 4. Analisa via IncidentCorrelationService
     * 5. Salva resultado em alb_logs_analysis.json
     *
     * @param string $incidentId Identificador do incidente (ex: INC-2026-001)
     * @param array $incidentData Dados do incidente (started_at, restored_at)
     * @return array Análise de logs (legitimate, suspicious, malicious IPs)
     */
    public function analyzeLogs(string $incidentId, array $incidentData): array
    {
        $this->checkLock($incidentId, 'analyze_logs');

        try {
            $this->acquireLock($incidentId, 'analyze_logs');

            // 1. Parse datas do incidente
            $started_at = Carbon::parse($incidentData['timestamp']['started_at'] ?? $incidentData['started_at'] ?? null);
            $restored_at = Carbon::parse($incidentData['timestamp']['restored_at'] ?? $incidentData['restored_at'] ?? null);

            if (! $started_at || ! $restored_at) {
                throw new \RuntimeException("Incident {$incidentId} missing started_at or restored_at");
            }

            // 2. Baixar logs do S3 para pasta específica do incidente
            $download_result = $this->s3Downloader->downloadLogsForIncident(
                $incidentId,
                $started_at,
                $restored_at
            );

            if ($download_result['downloaded_count'] === 0) {
                throw new \RuntimeException("No logs downloaded from S3 for {$incidentId}");
            }

            // 3. Ler logs do diretório específico do incidente
            $incident_logs_dir = "{$this->incidents_base_path}/.raw_logs/{$incidentId}";
            if (! File::isDirectory($incident_logs_dir)) {
                throw new \RuntimeException("Incident logs directory not found: {$incident_logs_dir}");
            }

            $log_files = File::glob("{$incident_logs_dir}/*.log");
            if (empty($log_files)) {
                throw new \RuntimeException("No .log files found in: {$incident_logs_dir}");
            }

            // Ler todas as linhas de todos os arquivos .log
            $logs = [];
            foreach ($log_files as $log_file) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $logs = array_merge($logs, $lines);
                }
            }

            if (empty($logs)) {
                throw new \RuntimeException("No log lines found in {$incident_logs_dir}/*.log");
            }

            // 4. Delegar análise para pacote
            $result = $this->correlationService->analyzeLogs($logs);

            // 5. Salvar resultado
            $incident_dir = "{$this->incidents_base_path}/{$incidentId}";
            if (! File::isDirectory($incident_dir)) {
                File::makeDirectory($incident_dir, 0755, true);
            }

            File::put(
                "{$incident_dir}/alb_logs_analysis.json",
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $result;
        } finally {
            $this->releaseLock($incidentId, 'analyze_logs');
        }
    }

    /**
     * 2. Correlaciona IPs afetados com usuários do banco
     *
     * @param string $incidentId Identificador do incidente
     * @param string $startTime ISO 8601 timestamp
     * @param string $endTime ISO 8601 timestamp
     * @return array Usuários afetados com logins e requests
     */
    public function correlateAffectedUsers(string $incidentId, string $startTime, string $endTime): array
    {
        $this->checkLock($incidentId, 'correlate_users');

        try {
            $this->acquireLock($incidentId, 'correlate_users');

            // Ler análise de logs para pegar IPs legítimos
            $alb_logs_file = "{$this->incidents_base_path}/{$incidentId}/alb_logs_analysis.json";
            if (! File::exists($alb_logs_file)) {
                throw new \RuntimeException('ALB logs analysis not found. Run analyzeLogs first.');
            }

            $analysis = json_decode(File::get($alb_logs_file), true);
            $legitimateIps = $analysis['classified']['legitimate'] ?? [];
            $suspiciousIps = $analysis['classified']['suspicious'] ?? [];
            
            // Combinar IPs legítimos e suspeitos para correlação
            $allIpsWithMetrics = array_merge($legitimateIps, $suspiciousIps);
            $ipErrorMetrics = [];
            foreach ($allIpsWithMetrics as $ipMetric) {
                $ipErrorMetrics[$ipMetric['ip']] = [
                    'total_requests' => $ipMetric['total_requests'] ?? 0,
                    'error_rate' => $ipMetric['error_rate'] ?? 0,
                ];
            }

            // Delegar para pacote
            $result = $this->correlationService->correlateAffectedUsers(
                $allIpsWithMetrics,
                ['start' => $startTime, 'end' => $endTime],
                null // organizationId opcional
            );
            
            // Enriquecer usuários com dados de erro dos ALB logs
            if (isset($result['users'])) {
                foreach ($result['users'] as &$user) {
                    $userIp = $user['ip'] ?? null;
                    if ($userIp && isset($ipErrorMetrics[$userIp])) {
                        $metrics = $ipErrorMetrics[$userIp];
                        // Adicionar erro_rate dos ALB logs
                        $albErrors = (int)ceil(($metrics['error_rate'] / 100) * $metrics['total_requests']);
                        // Somar com erros já contados do DB
                        $user['errors'] += $albErrors;
                        // Atualizar contagem de requests se o ALB teve mais
                        if ($metrics['total_requests'] > $user['requests']) {
                            $user['requests'] = $metrics['total_requests'];
                        }
                    }
                }
            }

            // Salvar resultado
            $analysis_dir = "{$this->incidents_base_path}/{$incidentId}";
            File::put(
                "{$analysis_dir}/affected_users.json",
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $result;
        } finally {
            $this->releaseLock($incidentId, 'correlate_users');
        }
    }

    /**
     * 3. Calcula métricas de impacto
     *
     * @param string $incidentId Identificador do incidente
     * @param string $startTime ISO 8601 timestamp
     * @param string $endTime ISO 8601 timestamp
     * @return array Métricas SRE (MTTR, affected_users, error_rate, etc)
     */
    public function calculateImpactMetrics(string $incidentId, string $startTime, string $endTime): array
    {
        $this->checkLock($incidentId, 'calculate_metrics');

        try {
            $this->acquireLock($incidentId, 'calculate_metrics');

            // Ler análise de usuários afetados
            $affected_users_file = "{$this->incidents_base_path}/{$incidentId}/affected_users.json";
            if (! File::exists($affected_users_file)) {
                throw new \RuntimeException('Affected users analysis not found. Run correlateAffectedUsers first.');
            }

            $affectedUsersData = json_decode(File::get($affected_users_file), true);
            $affectedUsers = $affectedUsersData['users'] ?? [];

            // Delegar para pacote
            $metrics = $this->correlationService->calculateImpactMetrics(
                $affectedUsers,
                ['start' => $startTime, 'end' => $endTime]
            );

            // Salvar resultado
            $analysis_dir = "{$this->incidents_base_path}/{$incidentId}";
            File::put(
                "{$analysis_dir}/incident_metrics.json",
                json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $metrics;
        } finally {
            $this->releaseLock($incidentId, 'calculate_metrics');
        }
    }

    /**
     * 4. Gera regras WAF recomendadas
     *
     * @param string $incidentId Identificador do incidente
     * @return array WAF rules (não aplicadas, apenas geradas)
     */
    public function generateWafRules(string $incidentId): array
    {
        // Ler análise de logs
        $alb_logs_file = "{$this->incidents_base_path}/{$incidentId}/alb_logs_analysis.json";
        if (! File::exists($alb_logs_file)) {
            throw new \RuntimeException('ALB logs analysis not found. Run analyzeLogs first.');
        }

        $analysis = json_decode(File::get($alb_logs_file), true);
        $malicious_ips = $analysis['classified']['malicious'] ?? [];

        // Delegar para pacote
        $rules = $this->correlationService->generateWAFRules($malicious_ips, $incidentId);

        // Salvar recomendação (não aplicada)
        $analysis_dir = "{$this->incidents_base_path}/{$incidentId}";
        File::put(
            "{$analysis_dir}/waf_rules_recommended.json",
            json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $rules;
    }

    /**
     * 5. Aplica regras WAF na AWS (ação destrutiva)
     *
     * @param string $incidentId Identificador do incidente
     * @param string $webAclId AWS WAF Web ACL ID
     * @param string $webAclName AWS WAF Web ACL Name
     * @return array Resultado da aplicação
     */
    public function applyWafRules(string $incidentId, string $webAclId, string $webAclName): array
    {
        // TODO: Implement AWS CLI integration for applying WAF rules
        // This requires AWS credentials and proper permissions
        // For now, return a placeholder response

        throw new \RuntimeException('WAF rule application not yet implemented. Please apply rules manually using AWS CLI or Console.');
    }

    /**
     * 6. Gera análise de impacto completa (orquestra fluxo completo)
     *
     * @param string $incidentId Identificador do incidente
     * @param array $incidentData Dados do incidente (started_at, restored_at)
     * @return array Analysis data consolidada
     */
    public function generateImpactAnalysis(string $incidentId, array $incidentData): array
    {
        $this->checkLock($incidentId, 'impact_analysis');

        try {
            $this->acquireLock($incidentId, 'impact_analysis');

            // Parse timestamps
            $startTime = $incidentData['start_time']
                ?? $incidentData['timestamp']['started_at']
                ?? now()->subHours(1)->toIso8601String();

            $endTime = $incidentData['end_time']
                ?? $incidentData['timestamp']['restored_at']
                ?? now()->toIso8601String();

            // Orquestrar fluxo completo
            $this->analyzeLogs($incidentId, $incidentData);
            $this->correlateAffectedUsers($incidentId, $startTime, $endTime);
            $metrics = $this->calculateImpactMetrics($incidentId, $startTime, $endTime);

            // Salvar impacto consolidado
            $analysis_dir = "{$this->incidents_base_path}/{$incidentId}";
            $alb_analysis = json_decode(File::get("{$analysis_dir}/alb_logs_analysis.json"), true);
            $affected_users = json_decode(File::get("{$analysis_dir}/affected_users.json"), true);

            $impact = [
                'incident_id' => $incidentId,
                'metrics' => $metrics,
                'alb_analysis' => $alb_analysis,
                'affected_users' => $affected_users,
                'generated_at' => now()->toIso8601String(),
            ];

            File::put(
                "{$analysis_dir}/impact.json",
                json_encode($impact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $impact;
        } finally {
            $this->releaseLock($incidentId, 'impact_analysis');
        }
    }

    /**
     * Lock mechanism para evitar processamento duplicado
     */
    private function getLockPath(string $incidentId, string $operation): string
    {
        return storage_path("locks/incident_{$incidentId}_{$operation}.lock");
    }

    private function checkLock(string $incidentId, string $operation): void
    {
        $lockPath = $this->getLockPath($incidentId, $operation);

        if (File::exists($lockPath)) {
            $lockTime = (int) File::get($lockPath);
            $age = time() - $lockTime;

            // Lock válido por 5 minutos
            if ($age < 300) {
                throw new \RuntimeException("PROCESSING_LOCKED: Operation already in progress (age: {$age}s)");
            }

            // Liberar lock expirado
            File::delete($lockPath);
        }
    }

    private function acquireLock(string $incidentId, string $operation): void
    {
        $lockDir = storage_path('locks');
        if (! File::exists($lockDir)) {
            File::makeDirectory($lockDir, 0755, true);
        }

        $lockPath = $this->getLockPath($incidentId, $operation);
        File::put($lockPath, (string) time());
    }

    private function releaseLock(string $incidentId, string $operation): void
    {
        $lockPath = $this->getLockPath($incidentId, $operation);
        if (File::exists($lockPath)) {
            File::delete($lockPath);
        }
    }
}
