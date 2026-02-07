<?php

namespace MatheusFS\Laravel\Insights\Http\Controllers\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
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
            abort(404, "Incidente {$incidentId} não encontrado");
        }

        return $this->incidentPdfGenerator->generateReceipt($incident, download: true);
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
            abort(404, "Incidente {$incidentId} não encontrado");
        }

        return $this->incidentPdfGenerator->generateReceipt($incident, download: false);
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
        $jsonPath = $incidentsPath ? rtrim($incidentsPath, '/').'/incidents.json' : null;

        if (! $jsonPath || ! File::exists($jsonPath)) {
            abort(500, 'Arquivo de incidentes não encontrado');
        }

        $jsonContent = File::get($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
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
}
