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
        // Diretório para JSON de incidentes
        $this->incidents_base_path = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
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
     * @param bool $force Forçar re-análise mesmo se cache existir
     * @return array Análise de logs (legitimate, suspicious, malicious IPs)
     */
    public function analyzeLogs(string $incidentId, array $incidentData, bool $force = false): array
    {
        // Verificar se análise já existe (cache)
        if (!$force) {
            $fileStorageService = app(\MatheusFS\Laravel\Insights\Services\Infrastructure\FileStorageService::class);
            try {
                $cached = $fileStorageService->readJsonData($incidentId, 'alb_logs_analysis');
                \Log::info("analyzeLogs: Cache hit for {$incidentId}, skipping analysis");
                return $cached;
            } catch (\RuntimeException $e) {
                // Cache não existe, segue fluxo normal
            }
        }
        
        $this->checkLock($incidentId, 'analyze_logs');

        try {
            $this->acquireLock($incidentId, 'analyze_logs');

            // 1. Parse datas do incidente
            $started_at = Carbon::parse($incidentData['timestamp']['started_at'] ?? $incidentData['started_at'] ?? null);
            $restored_at = Carbon::parse($incidentData['timestamp']['restored_at'] ?? $incidentData['restored_at'] ?? null);

            if (! $started_at || ! $restored_at) {
                throw new \RuntimeException("Incident {$incidentId} não pode ser analisado: incidente aberto (sem restored_at) ou started_at faltando. Incidentes abertos não têm período definido para análise de logs.");
            }

            // 2. Baixar logs do S3 para pasta específica do incidente
            // Nota: timestamps são agora carregados do JSON do incidente dentro do método
            $download_result = $this->s3Downloader->downloadLogsForIncident(
                $incidentId,
                useMargins: true,
                forceExtraction: false
            );

            if ($download_result['downloaded_count'] === 0 && $download_result['extracted_count'] === 0) {
                \Log::warning("No new logs downloaded for {$incidentId}; relying on existing extracted logs if available.");
            }

            // 3. Ler logs do diretório unificado de access logs
            $access_logs_dir = config('insights.access_logs_path', storage_path('insights/access-logs'));
            if (! File::isDirectory($access_logs_dir)) {
                throw new \RuntimeException("Access logs directory not found: {$access_logs_dir}");
            }

            $log_files = File::glob("{$access_logs_dir}/*.log");
            if (empty($log_files)) {
                throw new \RuntimeException("No .log files found in: {$access_logs_dir}");
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
                throw new \RuntimeException("No log lines found in {$access_logs_dir}/*.log");
            }

            // 4. Delegar análise para pacote
            $result = $this->correlationService->analyzeLogs($logs);

            // 5. Adicionar metadata do incidente ao resultado
            $result['incident_id'] = $incidentId;
            $result['started_at'] = $started_at->toIso8601String();
            $result['restored_at'] = $restored_at->toIso8601String();

            // 6. Salvar resultado em {incidents_path}/INC-ID/alb_logs_analysis.json
            $fileStorageService = app(\MatheusFS\Laravel\Insights\Services\Infrastructure\FileStorageService::class);
            $fileStorageService->saveJsonData($incidentId, 'alb_logs_analysis', $result);

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
     * @param bool $force Forçar re-correlação mesmo se cache existir
     * @return array Usuários afetados com logins e requests
     */
    public function correlateAffectedUsers(string $incidentId, string $startTime, string $endTime, bool $force = false): array
    {
        // Verificar se correlação já existe (cache)
        if (!$force) {
            $fileStorageService = app(\MatheusFS\Laravel\Insights\Services\Infrastructure\FileStorageService::class);
            try {
                $cached = $fileStorageService->readJsonData($incidentId, 'affected_users');
                \Log::info("correlateAffectedUsers: Cache hit for {$incidentId}, skipping correlation");
                return $cached;
            } catch (\RuntimeException $e) {
                // Cache não existe, segue fluxo normal
            }
        }
        
        $this->checkLock($incidentId, 'correlate_users');

        try {
            $this->acquireLock($incidentId, 'correlate_users');

            // Ler análise de logs para pegar IPs legítimos
            $fileStorageService = app(\MatheusFS\Laravel\Insights\Services\Infrastructure\FileStorageService::class);
            $analysis = $fileStorageService->readJsonData($incidentId, 'alb_logs_analysis');

            if (empty($analysis)) {
                throw new \RuntimeException('ALB logs analysis not found. Run analyzeLogs first.');
            }

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
                unset($user);

                $criticalThreshold = (float)config('insights.user_impact.critical_error_rate_min', 10.0);
                $criticalUsers = [];

                foreach ($result['users'] as &$user) {
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

                $result['critical_affected_users'] = $criticalUsers;
            }

            // Salvar resultado
            File::put(
                "{$this->incidents_base_path}/{$incidentId}/affected_users.json",
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $result;
        } finally {
            $this->releaseLock($incidentId, 'correlate_users');
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
        File::put(
            "{$this->incidents_base_path}/{$incidentId}/waf_rules_recommended.json",
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
