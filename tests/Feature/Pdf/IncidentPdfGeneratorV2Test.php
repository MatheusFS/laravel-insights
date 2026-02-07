<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature\Pdf;

use Illuminate\Support\Carbon;
use MatheusFS\Laravel\Insights\Services\Pdf\IncidentPdfGeneratorV2;
use MatheusFS\Laravel\Insights\Services\Pdf\PdfGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Incident PDF Generator Tests
 *
 * Testes unitários para validar:
 * - Cálculo de métricas SRE
 * - Formatação de datas/durações
 * - Enriquecimento de dados
 * - Transformação de tipos
 */
class IncidentPdfGeneratorV2Test extends TestCase
{
    protected IncidentPdfGeneratorV2 $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $pdfGeneratorMock = $this->createMock(PdfGenerator::class);
        $this->generator = new IncidentPdfGeneratorV2($pdfGeneratorMock);
    }

    public function test_format_duration_less_than_one_hour(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [45]);
        $this->assertEquals('45m', $result);
    }

    public function test_format_duration_one_hour(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [60]);
        $this->assertEquals('1h', $result);
    }

    public function test_format_duration_one_hour_thirty_minutes(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [90]);
        $this->assertEquals('1h 30m', $result);
    }

    public function test_format_duration_multiple_hours(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [150]);
        $this->assertEquals('2h 30m', $result);
    }

    public function test_format_duration_zero(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [0]);
        $this->assertEquals('0m', $result);
    }

    public function test_format_duration_null(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [null]);
        $this->assertEquals('—', $result);
    }

    public function test_format_duration_negative(): void
    {
        $result = $this->invokeMethod($this->generator, 'formatDuration', [-5]);
        $this->assertEquals('—', $result);
    }

    public function test_format_date_time_pt_br(): void
    {
        $date = Carbon::parse('2026-02-07 14:30:45', 'America/Sao_Paulo');
        $result = $this->invokeMethod($this->generator, 'formatDateTime', [$date]);

        $this->assertEquals('07/02/2026 14:30:45', $result);
    }

    public function test_calculate_metrics_complete(): void
    {
        $started = Carbon::parse('2026-02-07 10:15:00 America/Sao_Paulo');
        $detected = Carbon::parse('2026-02-07 10:18:00 America/Sao_Paulo');
        $restored = Carbon::parse('2026-02-07 10:34:30 America/Sao_Paulo');
        $resolved = Carbon::parse('2026-02-07 11:45:00 America/Sao_Paulo');

        $metrics = $this->invokeMethod($this->generator, 'calculateMetrics',
            [$started, $detected, $restored, $resolved]
        );

        $this->assertArrayHasKey('ttd', $metrics);
        $this->assertArrayHasKey('ttr', $metrics);
        $this->assertArrayHasKey('ttrad', $metrics);
        $this->assertArrayHasKey('ttc', $metrics);

        $this->assertEquals(3, $metrics['ttd']['minutes']);
        $this->assertEquals('3m', $metrics['ttd']['formatted']);

        $this->assertGreaterThan(15, $metrics['ttr']['minutes']);
        $this->assertEquals(90, $metrics['ttc']['minutes']);
        $this->assertEquals('1h 30m', $metrics['ttc']['formatted']);
    }

    public function test_calculate_metrics_partial(): void
    {
        $started = Carbon::parse('2026-02-07 13:00:00 America/Sao_Paulo');
        $detected = Carbon::parse('2026-02-07 13:08:00 America/Sao_Paulo');
        $restored = null;
        $resolved = null;

        $metrics = $this->invokeMethod($this->generator, 'calculateMetrics',
            [$started, $detected, $restored, $resolved]
        );

        $this->assertEquals(8, $metrics['ttd']['minutes']);
        $this->assertEquals('8m', $metrics['ttd']['formatted']);

        $this->assertNull($metrics['ttr']['minutes']);
        $this->assertEquals('—', $metrics['ttr']['formatted']);
    }

    public function test_get_severity_color_class_s0(): void
    {
        $color = $this->invokeMethod($this->generator, 'getSeverityColorClass', ['S0']);
        $this->assertEquals('badge-critical', $color);
    }

    public function test_get_severity_color_class_s1(): void
    {
        $color = $this->invokeMethod($this->generator, 'getSeverityColorClass', ['S1']);
        $this->assertEquals('badge-high', $color);
    }

    public function test_get_severity_color_class_s2(): void
    {
        $color = $this->invokeMethod($this->generator, 'getSeverityColorClass', ['S2']);
        $this->assertEquals('badge-medium', $color);
    }

    public function test_get_severity_color_class_s3(): void
    {
        $color = $this->invokeMethod($this->generator, 'getSeverityColorClass', ['S3']);
        $this->assertEquals('badge-low', $color);
    }

    public function test_get_status_label(): void
    {
        $this->assertEquals('Aberto',
            $this->invokeMethod($this->generator, 'getStatusLabel', ['open']));

        $this->assertEquals('Investigando',
            $this->invokeMethod($this->generator, 'getStatusLabel', ['investigating']));

        $this->assertEquals('Resolvido',
            $this->invokeMethod($this->generator, 'getStatusLabel', ['resolved']));

        $this->assertEquals('Mitigado',
            $this->invokeMethod($this->generator, 'getStatusLabel', ['mitigated']));
    }

    public function test_format_error_type(): void
    {
        $this->assertEquals('Erro Servidor (5xx)',
            $this->invokeMethod($this->generator, 'formatErrorType', ['5xx']));

        $this->assertEquals('Erro Cliente (4xx)',
            $this->invokeMethod($this->generator, 'formatErrorType', ['4xx']));

        $this->assertEquals('Latência Elevada',
            $this->invokeMethod($this->generator, 'formatErrorType', ['latency']));

        $this->assertEquals('Banco de Dados',
            $this->invokeMethod($this->generator, 'formatErrorType', ['database']));
    }

    public function test_prepare_data_complete_incident(): void
    {
        $incident = [
            'id' => 'INC-2026-0042',
            'status' => 'resolved',
            'environment' => 'production',
            'timestamp' => [
                'started_at' => '2026-02-07T10:15:00Z',
                'detected_at' => '2026-02-07T10:18:30Z',
                'restored_at' => '2026-02-07T10:35:00Z',
                'resolved_at' => '2026-02-07T11:45:00Z',
            ],
            'classification' => [
                'error_type' => '5xx',
                'severity' => 'CONTRATUAL',
                'severity_level' => 'S1',
                'metric_value' => 8.5,
                'metric_unit' => '%',
            ],
            'impact' => [
                'description' => 'Test impact',
                'users_affected' => 2145,
                'sla_breached' => true,
            ],
            'root_cause' => 'Test root cause',
            'remediation' => [
                'immediate' => 'Test immediate',
                'short_term' => 'Test short term',
                'long_term' => 'Test long term',
            ],
            'oncall' => 'Carlos Silva',
            'action_items' => ['Action 1', 'Action 2'],
            'artifacts_dir' => '/data/incidents/test/',
        ];

        $data = $this->invokeMethod($this->generator, 'prepareData', [$incident]);

        $this->assertArrayHasKey('incident', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('classification', $data);
        $this->assertArrayHasKey('impact', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('generated_at', $data);

        $this->assertEquals('INC-2026-0042', $data['incident']['id']);
        $this->assertEquals('PRODUCTION', $data['incident']['environment']);
        $this->assertEquals('Resolvido', $data['incident']['status_label']);

        $this->assertNotEmpty($data['timestamp']['started_at']);
        $this->assertNotEmpty($data['metrics']['ttd']['formatted']);
        $this->assertNotEmpty($data['metrics']['ttr']['formatted']);
    }

    public function test_prepare_data_incomplete_incident(): void
    {
        $incident = [
            'id' => 'INC-2026-0043',
            'status' => 'investigating',
            'timestamp' => [
                'started_at' => '2026-02-07T13:00:00Z',
                'detected_at' => '2026-02-07T13:08:00Z',
            ],
            'classification' => [
                'error_type' => 'database',
                'severity_level' => 'S0',
            ],
            'impact' => [
                'sla_breached' => true,
            ],
        ];

        $data = $this->invokeMethod($this->generator, 'prepareData', [$incident]);

        $this->assertEquals('INC-2026-0043', $data['incident']['id']);
        $this->assertEquals('Não registrado', $data['incident']['oncall']);
        $this->assertEquals('Não descrito', $data['impact']['description']);
        $this->assertNull($data['metrics']['ttr']['minutes']);
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
