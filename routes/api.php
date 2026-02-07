<?php

use Illuminate\Support\Facades\Route;
use MatheusFS\Laravel\Insights\Http\Controllers\IncidentAnalysisApiController;

/*
|--------------------------------------------------------------------------
| Package API Routes
|--------------------------------------------------------------------------
|
| Rotas REST para análise de incidentes
| Prefixo: /api/insights/reliability
|
*/

// GET /api/insights/reliability/incidents
// Lista todos os incidentes do arquivo consolidado
Route::get('incidents', [IncidentAnalysisApiController::class, 'listIncidents'])
    ->name('incidents.list');

Route::prefix('incidents/{incidentId}')
    ->name('incidents.')
    ->group(function () {
        
        // GET routes - Leitura de dados de incidentes
        Route::get('details', [IncidentAnalysisApiController::class, 'incidentDetails'])
            ->name('details');
        Route::get('alb-logs', [IncidentAnalysisApiController::class, 'incidentAlbLogs'])
            ->name('alb-logs');
        Route::get('affected-users', [IncidentAnalysisApiController::class, 'incidentAffectedUsers'])
            ->name('affected-users');

        // POST /api/insights/reliability/incidents/{incidentId}/analyze-logs
        Route::post('analyze-logs', [IncidentAnalysisApiController::class, 'analyzeLogs'])
            ->name('analyze-logs');

        // POST /api/insights/reliability/incidents/{incidentId}/correlate-users
        Route::post('correlate-users', [IncidentAnalysisApiController::class, 'correlateUsers'])
            ->name('correlate-users');

        // POST /api/insights/reliability/incidents/{incidentId}/generate-waf-rules
        Route::post('generate-waf-rules', [IncidentAnalysisApiController::class, 'generateWafRules'])
            ->name('generate-waf-rules');

        // POST /api/insights/reliability/incidents/{incidentId}/apply-waf-rules
        Route::post('apply-waf-rules', [IncidentAnalysisApiController::class, 'applyWafRules'])
            ->name('apply-waf-rules');
    });

// GET /api/insights/reliability/sre-metrics
// Retorna métricas SRE (SLI, SLO, SLA, Error Budget) para um período
// Query params: ?month=2026-02&slo_target=98.5&sla_target=95.0
Route::get('sre-metrics', [IncidentAnalysisApiController::class, 'calculateSREMetrics'])
    ->name('sre-metrics');

// GET /api/insights/reliability/sre-metrics/monthly (DEPRECATED)
// Use /sre-metrics instead
Route::get('sre-metrics/monthly', [IncidentAnalysisApiController::class, 'sreMonthlyMetrics'])
    ->name('sre-metrics.monthly');
