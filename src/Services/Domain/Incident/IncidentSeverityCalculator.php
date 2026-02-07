<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Incident;

/**
 * Calcula severity e severity_level baseado em error_type e metric_value
 * 
 * Esta lógica substitui os campos hardcoded no JSON
 * Campos devem ser calculados dinamicamente sempre que necessário
 */
class IncidentSeverityCalculator
{
    /**
     * Calcula severity e severity_level baseado em métricas
     *
     * @param string $errorType '5xx', '4xx', 'latency', 'database', 'external', 'other'
     * @param float $metricValue Valor da métrica
     * @param string $metricUnit '%', 'ms', 'count'
     * @return array ['severity' => string, 'severity_level' => string]
     */
    public function calculate(string $errorType, float $metricValue, string $metricUnit): array
    {
        $severity = $this->calculateSeverityLabel($errorType, $metricValue, $metricUnit);
        $severityLevel = $this->calculateSeverityLevel($severity);

        return [
            'severity' => $severity,
            'severity_level' => $severityLevel,
        ];
    }

    /**
     * Calcula o label de severity baseado em thresholds
     *
     * @param string $errorType
     * @param float $metricValue
     * @param string $metricUnit
     * @return string Severity label
     */
    private function calculateSeverityLabel(string $errorType, float $metricValue, string $metricUnit): string
    {
        switch ($errorType) {
            case '5xx':
                if ($metricValue >= 10) {
                    return 'Generalizado';
                } elseif ($metricValue >= 1 && $metricValue < 10) {
                    return 'BLOQUEANTE';
                } elseif ($metricValue >= 0.1 && $metricValue < 1) {
                    return 'Parcial';
                } else {
                    return 'Intermitente';
                }

            case '4xx':
                if ($metricValue >= 5 && $metricValue < 15) {
                    return 'Significativo';
                } elseif ($metricValue >= 0.5 && $metricValue < 5) {
                    return 'CONTRATUAL';
                } else {
                    return 'Negligenciável';
                }

            case 'latency':
                // metric_unit deve ser 'ms'
                if ($metricValue > 2000) {
                    return 'Degradação Severa';
                } elseif ($metricValue >= 800 && $metricValue <= 2000) {
                    return 'Degradação Média';
                } elseif ($metricValue >= 500 && $metricValue < 800) {
                    return 'Degradação Leve';
                } else {
                    return 'Normal';
                }

            case 'database':
            case 'external':
            case 'other':
            default:
                // Para tipos sem regra clara, inferir baseado em metric_value como %
                if ($metricValue >= 10) {
                    return 'Generalizado';
                } elseif ($metricValue >= 1) {
                    return 'BLOQUEANTE';
                } elseif ($metricValue >= 0.1) {
                    return 'Parcial';
                } else {
                    return 'Normal';
                }
        }
    }

    /**
     * Mapeia severity label para severity level (S0-S3)
     *
     * @param string $severity
     * @return string 'S0', 'S1', 'S2', 'S3'
     */
    private function calculateSeverityLevel(string $severity): string
    {
        return match ($severity) {
            'Generalizado' => 'S0', // Critical - impacto generalizado

            'BLOQUEANTE', 'CONTRATUAL', 'Degradação Severa' => 'S1', // High - impacto significativo

            'Parcial', 'Significativo', 'Degradação Média' => 'S2', // Medium - impacto moderado

            'Intermitente', 'Negligenciável', 'Degradação Leve', 'Normal' => 'S3', // Low - impacto mínimo

            default => 'S3',
        };
    }
}
