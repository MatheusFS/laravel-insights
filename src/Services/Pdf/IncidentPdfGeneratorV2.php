<?php

namespace MatheusFS\Laravel\Insights\Services\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Incident PDF Generator (V2)
 *
 * Generates professional, audit-ready PDF receipts for incident reports
 * following SRE standards, semantic structure, and Continuo Tecnologia branding.
 */
class IncidentPdfGeneratorV2
{
    /** @var PdfGenerator */
    protected PdfGenerator $pdfGenerator;

    public function __construct(PdfGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Generate Incident Receipt PDF
     *
     * @param array<string, mixed> $incident Incident data structure
     * @param bool $download If true, force download; if false, display inline
     * @return Response PDF response
     */
    public function generateReceipt(array $incident, bool $download = true): Response
    {
        $data = $this->prepareData($incident);

        $timestamp = $incident['timestamp'] ?? [];
        $detectedDate = isset($timestamp['detected_at'])
            ? Carbon::parse($timestamp['detected_at'])->format('Y-m-d')
            : now()->format('Y-m-d');

        $filename = sprintf(
            'incidente-%s-%s',
            $incident['id'] ?? 'unknown',
            $detectedDate
        );

        if ($download) {
            return $this->pdfGenerator->generate('insights::pdf.incidents.receipt_v2', $data, $filename);
        }

        return $this->pdfGenerator->stream('insights::pdf.incidents.receipt_v2', $data);
    }

    /**
     * Prepare enriched incident data for PDF view
     *
     * @param array<string, mixed> $incident Raw incident data
     * @return array<string, mixed> Enriched data with calculated metrics
     */
    protected function prepareData(array $incident): array
    {
        $timestamp = $incident['timestamp'] ?? [];
        $classification = $incident['classification'] ?? [];
        $impact = $incident['impact'] ?? [];
        $remediation = $incident['remediation'] ?? [];

        // Parse timestamps with timezone handling (UTC → São Paulo)
        $started = isset($timestamp['started_at'])
            ? Carbon::parse($timestamp['started_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $detected = isset($timestamp['detected_at'])
            ? Carbon::parse($timestamp['detected_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $restored = isset($timestamp['restored_at'])
            ? Carbon::parse($timestamp['restored_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $resolved = isset($timestamp['resolved_at'])
            ? Carbon::parse($timestamp['resolved_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;

        // Calculate SRE metrics in minutes
        $metrics = $this->calculateMetrics($started, $detected, $restored, $resolved);

        // Format dates for PT-BR display
        $timelineFormatted = [
            'started_at' => $started ? $this->formatDateTime($started) : 'Não registrado',
            'detected_at' => $detected ? $this->formatDateTime($detected) : 'Não registrado',
            'restored_at' => $restored ? $this->formatDateTime($restored) : 'Não registrado',
            'resolved_at' => $resolved ? $this->formatDateTime($resolved) : 'Não registrado',
        ];

        // Get severity color class
        $severityColor = $this->getSeverityColorClass($classification['severity_level'] ?? 'S3');
        $slaBreached = $impact['sla_breached'] ?? false;

        return [
            'incident' => [
                'id' => $incident['id'] ?? 'unknown',
                'status' => $incident['status'] ?? 'unknown',
                'status_label' => $this->getStatusLabel($incident['status'] ?? 'unknown'),
                'environment' => strtoupper($incident['environment'] ?? 'unknown'),
                'is_open' => in_array($incident['status'] ?? 'resolved', ['open', 'investigating', 'detected']),
                'oncall' => $incident['oncall'] ?? 'Não registrado',
                'artifacts_dir' => $incident['artifacts_dir'] ?? '',
            ],
            'timestamp' => $timelineFormatted,
            'classification' => [
                'error_type' => $this->formatErrorType($classification['error_type'] ?? 'unknown'),
                'severity' => $classification['severity'] ?? 'Normal',
                'severity_level' => $classification['severity_level'] ?? 'S3',
                'severity_color' => $severityColor,
                'metric_value' => $classification['metric_value'] ?? 0,
                'metric_unit' => $classification['metric_unit'] ?? '%',
            ],
            'impact' => [
                'description' => $impact['description'] ?? 'Não descrito',
                'users_affected' => $impact['users_affected'] ?? 0,
                'sla_breached' => $slaBreached,
                'sla_status' => $slaBreached ? 'SLA VIOLADO' : 'Dentro do SLA',
                'sla_class' => $slaBreached ? 'badge-critical' : 'badge-low',
            ],
            'root_cause' => $incident['root_cause'] ?? 'Investigação pendente',
            'remediation' => [
                'immediate' => $remediation['immediate'] ?? 'Não registrado',
                'short_term' => $remediation['short_term'] ?? 'Não registrado',
                'long_term' => $remediation['long_term'] ?? 'Não registrado',
            ],
            'action_items' => $incident['action_items'] ?? [],
            'metrics' => $metrics,
            'generated_at' => now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
            'company' => 'Continuo Tecnologia',
        ];
    }

    /**
     * Calculate SRE metrics from timestamps
     *
     * @param Carbon|null $started Início do incidente
     * @param Carbon|null $detected Detecção do incidente
     * @param Carbon|null $restored Serviço restaurado
     * @param Carbon|null $resolved Encerramento do incidente
     * @return array<string, mixed> Metrics with formatted strings
     */
    protected function calculateMetrics(?Carbon $started, ?Carbon $detected, ?Carbon $restored, ?Carbon $resolved): array
    {
        $ttd = null;
        $ttr = null;
        $ttrad = null;
        $ttc = null;

        if ($started && $detected) {
            $ttd = $started->diffInMinutes($detected);
        }

        if ($detected && $restored) {
            $ttr = $detected->diffInMinutes($restored);
        }

        if ($detected && $resolved) {
            $ttrad = $detected->diffInMinutes($resolved);
        }

        if ($started && $resolved) {
            $ttc = $started->diffInMinutes($resolved);
        }

        return [
            'ttd' => [
                'minutes' => $ttd,
                'formatted' => $this->formatDuration($ttd),
                'label' => 'TTD (Time To Detect)',
                'description' => 'Tempo desde o início até a detecção',
            ],
            'ttr' => [
                'minutes' => $ttr,
                'formatted' => $this->formatDuration($ttr),
                'label' => 'TTR (Time To Restore)',
                'description' => 'Tempo desde detecção até restauração',
            ],
            'ttrad' => [
                'minutes' => $ttrad,
                'formatted' => $this->formatDuration($ttrad),
                'label' => 'TTRAD (Time To Resolution And Document)',
                'description' => 'Tempo desde detecção até resolução',
            ],
            'ttc' => [
                'minutes' => $ttc,
                'formatted' => $this->formatDuration($ttc),
                'label' => 'TTC (Total Time Cycle)',
                'description' => 'Tempo total desde o início até resolução',
            ],
        ];
    }

    /**
     * Format duration in minutes to human-readable string
     */
    protected function formatDuration(?int $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '—';
        }

        if ($minutes === 0) {
            return '0m';
        }

        if ($minutes < 60) {
            return sprintf('%dm', $minutes);
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dh %dm', $hours, $mins);
    }

    /**
     * Format Carbon instance to PT-BR datetime string
     */
    protected function formatDateTime(Carbon $date): string
    {
        return $date->format('d/m/Y H:i:s');
    }

    /**
     * Format error type to human-readable label
     */
    protected function formatErrorType(string $errorType): string
    {
        return match ($errorType) {
            '5xx' => 'Erro Servidor (5xx)',
            '4xx' => 'Erro Cliente (4xx)',
            'latency' => 'Latência Elevada',
            'database' => 'Banco de Dados',
            'external' => 'Dependência Externa',
            'other' => 'Outro',
            default => 'Não especificado',
        };
    }

    /**
     * Map severity level to CSS class
     */
    protected function getSeverityColorClass(string $severityLevel): string
    {
        return match ($severityLevel) {
            'S0' => 'badge-critical',
            'S1' => 'badge-high',
            'S2' => 'badge-medium',
            'S3' => 'badge-low',
            default => 'badge-low',
        };
    }

    /**
     * Translate status to PT-BR label
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Aberto',
            'investigating' => 'Investigando',
            'detected' => 'Detectado',
            'mitigated' => 'Mitigado',
            'resolved' => 'Resolvido',
            default => 'Não definido',
        };
    }
}
