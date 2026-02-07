<?php

namespace MatheusFS\Laravel\Insights\Http\Controllers\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use MatheusFS\Laravel\Insights\Http\Controllers\Controller;
use MatheusFS\Laravel\Insights\Services\Pdf\IncidentPdfGeneratorV2;

/**
 * Incident PDF Controller
 *
 * Handles PDF generation for incident reports
 */
class IncidentPdfController extends Controller
{
    public function __construct(
        protected IncidentPdfGeneratorV2 $incidentPdfGenerator
    ) {}

    /**
     * Generate PDF for a specific incident
     *
     * @param  string  $incidentId  Incident ID (e.g., "INC-2026-001")
     */
    public function download(string $incidentId): Response
    {
        $incident = $this->findIncident($incidentId);

        if (! $incident) {
            Log::info('Incident PDF download: incidente não encontrado', [
                'incident_id' => $incidentId,
            ]);
            abort(404, "Incidente {$incidentId} não encontrado");
        }

        try {
            return $this->incidentPdfGenerator->generateReceipt($incident, download: true);
        } catch (\Throwable $exception) {
            Log::error('Incident PDF download: falha ao gerar PDF', [
                'incident_id' => $incidentId,
                'exception' => $exception->getMessage(),
            ]);

            abort(500, 'Erro ao gerar PDF do incidente');
        }
    }

    /**
     * Preview PDF in browser
     *
     * @param  string  $incidentId  Incident ID
     */
    public function preview(string $incidentId): Response
    {
        $incident = $this->findIncident($incidentId);

        if (! $incident) {
            Log::info('Incident PDF preview: incidente não encontrado', [
                'incident_id' => $incidentId,
            ]);
            abort(404, "Incidente {$incidentId} não encontrado");
        }

        try {
            return $this->incidentPdfGenerator->generateReceipt($incident, download: false);
        } catch (\Throwable $exception) {
            Log::error('Incident PDF preview: falha ao gerar PDF', [
                'incident_id' => $incidentId,
                'exception' => $exception->getMessage(),
            ]);

            abort(500, 'Erro ao gerar PDF do incidente');
        }
    }

    /**
     * Find incident by ID in JSON file
     *
     * @param  string  $incidentId  Incident ID to search
     * @return array|null Incident data or null if not found
     */
    protected function findIncident(string $incidentId): ?array
    {
        $incidentsPath = config('insights.incidents_path');
        $jsonPath = $this->resolveIncidentsJsonPath($incidentsPath);

        if (! $jsonPath || ! File::exists($jsonPath)) {
            Log::error('Incident PDF: arquivo de incidentes não encontrado', [
                'incident_id' => $incidentId,
                'incidents_path' => $incidentsPath,
                'json_path' => $jsonPath,
            ]);
            abort(500, 'Arquivo de incidentes não encontrado');
        }

        $jsonContent = File::get($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Incident PDF: erro ao decodificar JSON', [
                'incident_id' => $incidentId,
                'json_path' => $jsonPath,
                'json_error' => json_last_error_msg(),
            ]);
            abort(500, 'Erro ao decodificar JSON de incidentes');
        }

        $incidents = $data['incidents'] ?? [];

        foreach ($incidents as $incident) {
            if (($incident['id'] ?? null) === $incidentId) {
                return $incident;
            }
        }

        return null;
    }

    /**
     * Resolve o caminho do incidents.json consolidado.
     *
     * Regras:
     * - Se INSIGHTS_INCIDENTS_PATH apontar para arquivo .json, usar direto.
     * - Se apontar para diretório .../incidents, usar o parent + /incidents.json.
     * - Caso contrário, usar {path}/incidents.json.
     */
    protected function resolveIncidentsJsonPath(?string $incidentsPath): ?string
    {
        if (! $incidentsPath) {
            return null;
        }

        $normalized = rtrim($incidentsPath, '/');

        if (str_ends_with($normalized, '.json')) {
            return $normalized;
        }

        if (basename($normalized) === 'incidents') {
            return dirname($normalized).'/incidents.json';
        }

        return $normalized.'/incidents.json';
    }
}
