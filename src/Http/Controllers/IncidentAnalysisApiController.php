<?php

namespace MatheusFS\Laravel\Insights\Http\Controllers;

use MatheusFS\Laravel\Insights\Http\Requests\AnalyzeLogsRequest;
use MatheusFS\Laravel\Insights\Http\Requests\ApplyWAFRulesRequest;
use MatheusFS\Laravel\Insights\Http\Requests\CorrelateUsersRequest;
use MatheusFS\Laravel\Insights\Http\Requests\GenerateWAFRulesRequest;
use MatheusFS\Laravel\Insights\Services\Application\IncidentAnalysisService;
use MatheusFS\Laravel\Insights\Services\Domain\Metrics\SREMetricsCalculator;
use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * IncidentAnalysisApiController - API REST para análise de incidentes
 *
 * Endpoints públicos do package para integração
 * Aplicações devem fornecer incident_data e timestamps explicitamente
 */
class IncidentAnalysisApiController extends Controller
{
    public function __construct(
        private IncidentAnalysisService $analysisService,
        private SREMetricsCalculator $sreMetrics,
        private ALBLogDownloaderInterface $albDownloader
    ) {}

    /**
     * POST /api/insights/reliability/incidents/{incidentId}/analyze-logs
     *
     * Analisa logs ALB do incidente
     * 
     * Request Body:
     * {
     *   "incident_data": {
     *     "timestamp": {
     *       "started_at": "2026-01-15T10:00:00Z",
     *       "restored_at": "2026-01-15T10:30:00Z"
     *     }
     *   }
     * }
     */
    public function analyzeLogs(AnalyzeLogsRequest $request, string $incidentId): JsonResponse
    {
        try {
            $result = $this->analysisService->analyzeLogs(
                $incidentId,
                $request->validated('incident_data')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'PROCESSING_LOCKED')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Processamento já em andamento para este incidente',
                    'incident_id' => $incidentId,
                ], 409);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/insights/reliability/incidents/{incidentId}/correlate-users
     *
     * Correlaciona IPs afetados com usuários
     * 
     * Request Body:
     * {
     *   "start_time": "2026-01-15T10:00:00Z",
     *   "end_time": "2026-01-15T10:30:00Z"
     * }
     */
    public function correlateUsers(CorrelateUsersRequest $request, string $incidentId): JsonResponse
    {
        try {
            $result = $this->analysisService->correlateAffectedUsers(
                $incidentId,
                $request->validated('start_time'),
                $request->validated('end_time')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'PROCESSING_LOCKED')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Processamento já em andamento para este incidente',
                    'incident_id' => $incidentId,
                ], 409);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/insights/reliability/incidents/{incidentId}/calculate-metrics
     *
     * Calcula métricas de impacto
     * 
     * Request Body:
     * {
     *   "start_time": "2026-01-15T10:00:00Z",
     *   "end_time": "2026-01-15T10:30:00Z"
     * }
     */
    public function calculateMetrics(CorrelateUsersRequest $request, string $incidentId): JsonResponse
    {
        try {
            $metrics = $this->analysisService->calculateImpactMetrics(
                $incidentId,
                $request->validated('start_time'),
                $request->validated('end_time')
            );

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'message' => 'Metrics calculated and saved to incident_metrics.json',
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'PROCESSING_LOCKED')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Processamento já em andamento para este incidente',
                    'incident_id' => $incidentId,
                ], 409);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/insights/reliability/incidents/{incidentId}/generate-waf-rules
     *
     * Gera regras WAF recomendadas
     */
    public function generateWafRules(GenerateWAFRulesRequest $request, string $incidentId): JsonResponse
    {
        try {
            $result = $this->analysisService->generateWafRules($incidentId);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'WAF rules generated. Review waf_rules_recommended.json before applying.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/insights/reliability/incidents/{incidentId}/apply-waf-rules
     *
     * Aplica regras WAF na AWS
     * 
     * Request Body:
     * {
     *   "web_acl_id": "arn:aws:wafv2:...",
     *   "web_acl_name": "RefresherWebACL",
     *   "auto_apply": false,
     *   "dry_run": true
     * }
     */
    public function applyWafRules(ApplyWAFRulesRequest $request, string $incidentId): JsonResponse
    {
        try {
            $result = $this->analysisService->applyWafRules(
                $incidentId,
                $request->validated('web_acl_id'),
                $request->validated('web_acl_name')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'WAF rules applied successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to apply WAF rules',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/insights/reliability/incidents/{incidentId}/details
     *
     * Retorna dados detalhados de um incidente específico
     * Lê da pasta configurada em insights.incidents_path
     */
    public function incidentDetails(string $incidentId): JsonResponse
    {
        $incidents_path = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
        $impact_file = $incidents_path.'/'.$incidentId.'/incident_metrics.json';

        if (!file_exists($impact_file)) {
            return response()->json([
                'error' => 'Impacto não disponível para este incidente',
                'incident_id' => $incidentId,
                'path' => $impact_file,
            ], 404);
        }

        try {
            $impact_data = json_decode(file_get_contents($impact_file), true);

            // Ler também as timeseries para gráficos
            $traffic_file = $incidents_path.'/'.$incidentId.'/traffic.json';
            $errors_5xx_file = $incidents_path.'/'.$incidentId.'/errors_5xx.json';
            $errors_4xx_file = $incidents_path.'/'.$incidentId.'/errors_4xx.json';
            $alb_logs_file = $incidents_path.'/'.$incidentId.'/alb_logs_analysis.json';
            $affected_users_file = $incidents_path.'/'.$incidentId.'/affected_users.json';

            $response = [
                'impact' => $impact_data,
                'timeseries' => [
                    'traffic' => file_exists($traffic_file) ? json_decode(file_get_contents($traffic_file), true) : [],
                    'errors_5xx' => file_exists($errors_5xx_file) ? json_decode(file_get_contents($errors_5xx_file), true) : [],
                    'errors_4xx' => file_exists($errors_4xx_file) ? json_decode(file_get_contents($errors_4xx_file), true) : [],
                ],
                'alb_analysis' => file_exists($alb_logs_file) ? json_decode(file_get_contents($alb_logs_file), true) : null,
                'affected_users' => file_exists($affected_users_file) ? json_decode(file_get_contents($affected_users_file), true) : null,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao ler dados do incidente',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/insights/reliability/incidents/{incidentId}/alb-logs
     *
     * Retorna análise de ALB logs: IPs afetados, paths, distribuição de erro
     */
    public function incidentAlbLogs(string $incidentId): JsonResponse
    {
        $incidents_path = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
        $alb_logs_file = $incidents_path.'/'.$incidentId.'/alb_logs_analysis.json';

        if (!file_exists($alb_logs_file)) {
            return response()->json([
                'error' => 'Análise de ALB logs não disponível',
                'incident_id' => $incidentId,
                'path' => $alb_logs_file,
            ], 404);
        }

        try {
            $content = file_get_contents($alb_logs_file);
            $alb_data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'warning' => 'Arquivo de ALB logs está vazio ou inválido',
                    'path' => $alb_logs_file,
                    'alb_analysis' => null,
                ], 200);
            }
            
            return response()->json($alb_data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao ler análise de ALB logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/insights/reliability/incidents/{incidentId}/affected-users
     *
     * Retorna lista de usuários afetados com suas jornadas
     */
    public function incidentAffectedUsers(string $incidentId): JsonResponse
    {
        $incidents_path = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
        $affected_users_file = $incidents_path.'/'.$incidentId.'/affected_users.json';

        if (!file_exists($affected_users_file)) {
            return response()->json([
                'error' => 'Dados de usuários afetados não disponível',
                'incident_id' => $incidentId,
                'path' => $affected_users_file,
            ], 404);
        }

        try {
            $content = file_get_contents($affected_users_file);
            $affected_users = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'warning' => 'Arquivo de usuários afetados está vazio ou inválido',
                    'path' => $affected_users_file,
                    'affected_users' => null,
                ], 200);
            }
            
            return response()->json($affected_users);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao ler dados de usuários afetados',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/insights/reliability/sre-metrics
     *
     * Calcula métricas SRE (SLI, SLO, SLA, Error Budget) de logs contínuos
     * 
     * Query params:
     * - month: Período em formato Y-m (padrão: mês atual, ex: 2026-02)
     * - slo_target: Meta SLO (padrão: 98.5%)
     * - sla_target: Meta SLA (padrão: 95%)
     * 
     * Retorna métricas separadas por serviço (API e UI)
     * Fonte de dados: Logs ALB agregados diariamente (contínuos)
     */
    public function calculateSREMetrics(): JsonResponse
    {
        try {
            // Use config defaults for API (can be overridden via query params)
            $slo_target = (float) request()->query('slo_target', config('insights.sre_targets.API.slo'));
            $sla_target = (float) request()->query('sla_target', config('insights.sre_targets.API.sla'));
            $month = request()->query('month', now()->format('Y-m'));

            // Validar formato do mês
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid month format. Use YYYY-MM (e.g., 2026-02)',
                ], 422);
            }

            // Usar novo método com logs contínuos
            $this->sreMetrics->setALBDownloader($this->albDownloader);
            $metrics = $this->sreMetrics->calculateMonthlyFromContinuousLogs(
                $month,
                $slo_target,
                $sla_target
            );

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            \Log::error('SRE Metrics Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to calculate SRE metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alias para calculateSREMetrics (compatibilidade com antiga rota)
     */
    public function sreMonthlyMetrics(): JsonResponse
    {
        return $this->calculateSREMetrics();
    }

    /**
     * GET /api/insights/reliability/incidents
     *
     * Lista todos os incidentes do arquivo consolidado
     */
    public function listIncidents(): JsonResponse
    {
        try {
            $incidentsPath = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
            $parentPath = dirname($incidentsPath);
            $incidentsFile = $parentPath . '/incidents.json';

            if (!\File::exists($incidentsFile)) {
                return response()->json([
                    'error' => 'Arquivo não encontrado',
                    'file' => 'incidents.json',
                    'path' => $incidentsFile,
                    'hint' => 'Arquivo de incidentes não encontrado. Verifique o caminho.',
                ], 404);
            }

            $incidentsJson = \File::get($incidentsFile);
            $allIncidents = json_decode($incidentsJson, true);

            if (!$allIncidents) {
                return response()->json([
                    'error' => 'JSON inválido',
                    'file' => 'incidents.json',
                    'path' => $incidentsFile,
                ], 500);
            }

            // Normalizar estrutura (adicionar campos calculados se necessário)
            $incidents = $allIncidents['incidents'] ?? [];
            
            return response()->json([
                'metadata' => $allIncidents['metadata'] ?? [],
                'incidents' => $incidents,
                'total' => count($incidents),
            ]);

        } catch (\Exception $e) {
            \Log::error('List Incidents Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Erro ao listar incidentes',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

}

