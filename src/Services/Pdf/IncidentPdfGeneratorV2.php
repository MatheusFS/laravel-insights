<?php

namespace MatheusFS\Laravel\Insights\Services\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;

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
        
        // Load affected users from separate JSON file
        $affectedUsersCount = $this->loadAffectedUsersCount($incident['id'] ?? 'unknown');

        // Parse timestamps with timezone handling (UTC → São Paulo)
        $started = isset($timestamp['started_at'])
            ? Carbon::parse($timestamp['started_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $detected = isset($timestamp['detected_at'])
            ? Carbon::parse($timestamp['detected_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $classificated = isset($timestamp['classificated_at'])
            ? Carbon::parse($timestamp['classificated_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $restored = isset($timestamp['restored_at'])
            ? Carbon::parse($timestamp['restored_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;
        $resolved = isset($timestamp['closed_at'])
            ? Carbon::parse($timestamp['closed_at'], 'UTC')->setTimezone('America/Sao_Paulo')
            : null;

        // Calculate SRE metrics in minutes
        $metrics = $this->calculateMetrics($started, $detected, $classificated, $restored, $resolved);

        // Format dates for PT-BR display
        $timelineFormatted = [
            'started_at' => $started ? $this->formatDateTime($started) : 'Não registrado',
            'detected_at' => $detected ? $this->formatDateTime($detected) : 'Não registrado',
            'classificated_at' => $classificated ? $this->formatDateTime($classificated) : 'Não registrado',
            'restored_at' => $restored ? $this->formatDateTime($restored) : 'Não registrado',
            'closed_at' => $resolved ? $this->formatDateTime($resolved) : 'Não registrado',
        ];

        // Get severity color class
        $calculator = new \MatheusFS\Laravel\Insights\Services\Domain\Incident\IncidentSeverityCalculator();
        $calculated = $calculator->calculate(
            $classification['error_type'] ?? 'other',
            $classification['metric_value'] ?? 0,
            $classification['metric_unit'] ?? '%'
        );
        $severityColor = $this->getSeverityColor($calculated['severity_level']);
        $slaBreached = $impact['sla_breached'] ?? false;

        return [
            'incident' => [
                'id' => $this->ensureString($incident['id'] ?? 'unknown'),
                'status' => $this->ensureString($incident['status'] ?? 'unknown'),
                'status_label' => $this->getStatusLabel($this->ensureString($incident['status'] ?? 'unknown')),
                'is_open' => in_array($this->ensureString($incident['status'] ?? 'resolved'), ['open', 'investigating', 'detected']),
                'oncall' => $this->ensureString($incident['oncall'] ?? 'Não registrado'),
                'artifacts_dir' => $this->ensureString($incident['artifacts_dir'] ?? ''),
                'severity_color' => $severityColor,
            ],
            'timestamp' => $timelineFormatted,
            'classification' => [
                'error_type' => $this->formatErrorType($this->ensureString($classification['error_type'] ?? 'unknown')),
                'severity' => $calculated['severity'],
                'severity_level' => $calculated['severity_level'],
                'severity_color' => $severityColor,
                'metric_value' => $classification['metric_value'] ?? 0,
                'metric_unit' => $this->ensureString($classification['metric_unit'] ?? '%'),
            ],
            'impact' => [
                'description' => $this->ensureString($impact['description'] ?? 'Não descrito'),
                'users_affected' => $affectedUsersCount,
                'sla_breached' => $slaBreached,
                'sla_status' => $slaBreached ? 'SLA VIOLADO' : 'Dentro do SLA',
                'sla_class' => $slaBreached ? 'badge-critical' : 'badge-low',
            ],
            'root_cause' => $this->ensureString($incident['root_cause'] ?? 'Investigação pendente'),
            'remediation' => [
                'immediate' => $this->ensureString($remediation['immediate'] ?? 'Não registrado'),
                'short_term' => $this->ensureString($remediation['short_term'] ?? 'Não registrado'),
                'long_term' => $this->ensureString($remediation['long_term'] ?? 'Não registrado'),
            ],
            'action_items' => $this->ensureArrayOfStrings($incident['action_items'] ?? []),
            'metrics' => [
                'ttd' => $metrics['ttd']['formatted'] ?? '—',
                'ttcy' => $metrics['ttcy']['formatted'] ?? '—',
                'ttr' => $metrics['ttr']['formatted'] ?? '—',
                'ttrad' => $metrics['ttrad']['formatted'] ?? '—',
                'ttc' => $metrics['ttc']['formatted'] ?? '—',
            ],
            'generated_at' => now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
            'company' => 'Continuo Tecnologia',
            'icons' => EmojiPath::getPdfIconArray(),  // Use base64 data URIs for PDF compatibility
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
    protected function calculateMetrics(
        ?Carbon $started,
        ?Carbon $detected,
        ?Carbon $classificated,
        ?Carbon $restored,
        ?Carbon $resolved
    ): array
    {
        $ttd = null;
        $ttcy = null;
        $ttr = null;
        $ttrad = null;
        $ttc = null;

        if ($started && $detected) {
            $ttd = $started->diffInMinutes($detected);
        }

        if ($detected && $classificated) {
            $ttcy = $detected->diffInMinutes($classificated);
        }

        if ($started && $restored) {
            $ttr = $started->diffInMinutes($restored);
        }

        if ($detected && $restored) {
            $ttrad = $detected->diffInMinutes($restored);
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
            'ttcy' => [
                'minutes' => $ttcy,
                'formatted' => $this->formatDuration($ttcy),
                'label' => 'TTCY (Time To Classify)',
                'description' => 'Tempo desde a detecção até a classificação',
            ],
            'ttr' => [
                'minutes' => $ttr,
                'formatted' => $this->formatDuration($ttr),
                'label' => 'TTR (Time To Restore)',
                'description' => 'Tempo desde o início até a restauração',
            ],
            'ttrad' => [
                'minutes' => $ttrad,
                'formatted' => $this->formatDuration($ttrad),
                'label' => 'TTRAD (Time To Restore After Detection)',
                'description' => 'Tempo desde a detecção até a restauração',
            ],
            'ttc' => [
                'minutes' => $ttc,
                'formatted' => $this->formatDuration($ttc),
                'label' => 'TTC (Total To Close)',
                'description' => 'Tempo total desde o início até o encerramento',
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
     * Map severity level to HEX color for PDFs
     */
    protected function getSeverityColor(string $severityLevel): string
    {
        return match ($severityLevel) {
            'S0' => '#d32f2f',  // Critical - Red
            'S1' => '#f57c00',  // High - Orange
            'S2' => '#fbc02d',  // Medium - Yellow
            'S3' => '#388e3c',  // Low - Green
            default => '#388e3c',
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

    /**
     * Load affected users count from separate JSON file
     *
     * @param string $incidentId Incident ID (e.g., "INC-2026-001")
     * @param string|null $basePath Base path to incidents directory
     * @return int Total count of affected users
     */
    protected function loadAffectedUsersCount(string $incidentId, ?string $basePath = null): int
    {
        $basePath = $basePath ?? config('insights.incidents_path');
        $affectedUsersPath = $basePath ? rtrim($basePath, '/') . '/' . $incidentId . '/affected_users.json' : null;

        if (! $affectedUsersPath || ! File::exists($affectedUsersPath)) {
            return 0;
        }

        try {
            $content = File::get($affectedUsersPath);
            $data = json_decode($content, true);
            return $data['total'] ?? 0;
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Ensure a value is a string (convert arrays to string)
     */
    protected function ensureString($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter($value, 'is_scalar'));
        }
        return (string) $value;
    }

    /**
     * Ensure a value is an array of strings
     */
    protected function ensureArrayOfStrings($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_map(fn($item) => $this->ensureString($item), $value);
    }
}
