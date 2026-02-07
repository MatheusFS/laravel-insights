<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Infrastructure;

use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use MatheusFS\Laravel\Insights\InsightsServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

class S3LogDownloaderServiceTest extends TestCase
{
    private S3LogDownloaderService $service;
    private string $tempIncidentsPath;
    private string $tempLogsPath;

    protected function getPackageProviders($app)
    {
        return [InsightsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Setup temp directories para testes
        $this->tempIncidentsPath = storage_path('app/test-incidents');
        $this->tempLogsPath = storage_path('app/test-access-logs');

        mkdir($this->tempIncidentsPath, 0755, true);
        mkdir($this->tempLogsPath, 0755, true);

        // Configure test paths
        config()->set('insights.incidents_path', $this->tempIncidentsPath);
        config()->set('insights.access_logs_path', $this->tempLogsPath);
        config()->set('insights.alb_logs.s3.bucket', 'test-bucket');
        config()->set('insights.alb_logs.s3.path', 'AWSLogs/123456789/elasticloadbalancing/us-east-1');
        config()->set('insights.alb_logs.s3.region', 'us-east-1');

        // Create new service with configured paths
        $this->service = new S3LogDownloaderService();
    }

    protected function tearDown(): void
    {
        // Cleanup
        if (is_dir($this->tempIncidentsPath)) {
            File::deleteDirectory($this->tempIncidentsPath);
        }
        if (is_dir($this->tempLogsPath)) {
            File::deleteDirectory($this->tempLogsPath);
        }
        parent::tearDown();
    }

    /**
     * AC: Método deve carregar timestamps do JSON do incidente
     */
    public function test_load_incident_from_json(): void
    {
        // Arrange
        $incidentId = 'INC-2026-001';
        $incidentData = [
            'id' => $incidentId,
            'started_at' => '2026-02-02T22:25:00Z',
            'restored_at' => '2026-02-03T12:50:00Z',
        ];

        $incidentFile = $this->tempIncidentsPath . '/' . $incidentId . '.json';
        file_put_contents($incidentFile, json_encode($incidentData));

        // Act & Assert - should not throw exception
        $this->assertFileExists($incidentFile);
        $content = json_decode(file_get_contents($incidentFile), true);
        $this->assertEquals($incidentData['started_at'], $content['started_at']);
    }

    /**
     * AC: Método deve lançar exceção se incidente não for encontrado
     */
    public function test_throws_exception_when_incident_not_found(): void
    {
        // Arrange - criar arquivo consolidado mas sem o incidente buscado
        $incidentsData = [
            'incidents' => [
                ['id' => 'INC-OTHER', 'started_at' => '2026-01-01T00:00:00Z', 'restored_at' => '2026-01-01T01:00:00Z']
            ]
        ];
        $parentPath = dirname($this->tempIncidentsPath);
        $incidentsFile = $parentPath . '/incidents.json';
        file_put_contents($incidentsFile, json_encode($incidentsData));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Incident not found');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('loadIncident');
        $method->setAccessible(true);
        $method->invoke($this->service, 'NON-EXISTENT');
    }

    /**
     * AC: Método deve lançar exceção se JSON do incidente for inválido
     */
    public function test_throws_exception_when_incident_json_is_invalid(): void
    {
        // Arrange - criar arquivo consolidado inválido
        $parentPath = dirname($this->tempIncidentsPath);
        $incidentsFile = $parentPath . '/incidents.json';
        file_put_contents($incidentsFile, 'invalid json {{{');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid incidents JSON');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('loadIncident');
        $method->setAccessible(true);
        $method->invoke($this->service, 'INC-TEST');
    }

    /**
     * AC: Método deve lançar exceção se faltar started_at no incidente
     */
    public function test_throws_exception_when_started_at_missing(): void
    {
        // Arrange - criar arquivo consolidado com incidente faltando started_at
        $incidentsData = [
            'incidents' => [
                ['id' => 'INC-MISSING-START', 'restored_at' => '2026-02-03T12:50:00Z']
            ]
        ];
        $parentPath = dirname($this->tempIncidentsPath);
        $incidentsFile = $parentPath . '/incidents.json';
        file_put_contents($incidentsFile, json_encode($incidentsData));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Incident missing started_at timestamp');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('loadIncident');
        $method->setAccessible(true);
        $method->invoke($this->service, 'INC-MISSING-START');
    }

    /**
     * AC: Método deve lançar exceção se faltar restored_at no incidente
     */
    public function test_throws_exception_when_restored_at_missing(): void
    {
        // Arrange - criar arquivo consolidado com incidente faltando restored_at
        $incidentsData = [
            'incidents' => [
                ['id' => 'INC-MISSING-RESTORE', 'started_at' => '2026-02-02T22:25:00Z']
            ]
        ];
        $parentPath = dirname($this->tempIncidentsPath);
        $incidentsFile = $parentPath . '/incidents.json';
        file_put_contents($incidentsFile, json_encode($incidentsData));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Incident missing restored_at timestamp');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('loadIncident');
        $method->setAccessible(true);
        $method->invoke($this->service, 'INC-MISSING-RESTORE');
    }

    /**
     * AC: generateS3Prefixes deve gerar lista correta de prefixos YYYY/MM/DD/
     */
    public function test_generate_s3_prefixes_for_same_day(): void
    {
        // Arrange
        $start = Carbon::parse('2026-02-02T22:25:00Z');
        $end = Carbon::parse('2026-02-02T23:50:00Z');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateS3Prefixes');
        $method->setAccessible(true);
        $prefixes = $method->invoke($this->service, $start, $end);

        // Assert
        $this->assertCount(1, $prefixes);
        $this->assertEquals('2026/02/02/', $prefixes[0]);
    }

    /**
     * AC: generateS3Prefixes deve gerar prefixos para múltiplos dias
     */
    public function test_generate_s3_prefixes_for_multiple_days(): void
    {
        // Arrange
        $start = Carbon::parse('2026-02-02T22:25:00Z');
        $end = Carbon::parse('2026-02-05T12:50:00Z');

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateS3Prefixes');
        $method->setAccessible(true);
        $prefixes = $method->invoke($this->service, $start, $end);

        // Assert
        $this->assertCount(4, $prefixes);
        $this->assertEquals('2026/02/02/', $prefixes[0]);
        $this->assertEquals('2026/02/03/', $prefixes[1]);
        $this->assertEquals('2026/02/04/', $prefixes[2]);
        $this->assertEquals('2026/02/05/', $prefixes[3]);
    }

    /**
     * AC: extractTimestampFromFilename deve extrair timestamp no formato YYYYMMDDTHHmmZ
     */
    public function test_extract_timestamp_from_alb_filename(): void
    {
        // Arrange
        $filename = '624082998591_elasticloadbalancing_us-east-1_app.production.6bed1cf9aa718eab_20260202T2225Z_54.85.26.63_3798xgq1.log.gz';

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTimestampFromFilename');
        $method->setAccessible(true);
        $timestamp = $method->invoke($this->service, $filename);

        // Assert
        $this->assertEquals('20260202T2225Z', $timestamp);
    }

    /**
     * AC: extractTimestampFromFilename deve retornar null para nomes inválidos
     */
    public function test_extract_timestamp_returns_null_for_invalid_filename(): void
    {
        // Arrange
        $filename = 'invalid_filename.log.gz';

        // Act
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTimestampFromFilename');
        $method->setAccessible(true);
        $timestamp = $method->invoke($this->service, $filename);

        // Assert
        $this->assertNull($timestamp);
    }

    /**
     * AC: hasAvailableLogs deve retornar false se nenhum arquivo existe
     */
    public function test_has_available_logs_returns_false_when_empty(): void
    {
        $hasLogs = $this->service->hasAvailableLogs();

        $this->assertFalse($hasLogs);
    }

    /**
     * AC: hasAvailableLogs deve retornar true se arquivos .log existem
     */
    public function test_has_available_logs_returns_true_when_logs_exist(): void
    {
        // Arrange
        touch($this->tempLogsPath . '/test.log');

        // Act
        $hasLogs = $this->service->hasAvailableLogs();

        // Assert
        $this->assertTrue($hasLogs);
    }

    /**
     * AC: listLogsForIncident deve retornar lista de arquivos do diretório
     */
    public function test_list_logs_for_incident(): void
    {
        // Arrange
        $filename = '624082998591_elasticloadbalancing_us-east-1_app.production.xxx_20260202T2225Z_1.1.1.1_abc.log';
        touch($this->tempLogsPath . '/' . $filename);

        // Act
        $logs = $this->service->listLogsForIncident('INC-2026-001');

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
    }

    /**
     * AC: listAllFiles deve retornar arquivos .log e .gz separados
     */
    public function test_list_all_files_separates_extracted_and_compressed(): void
    {
        // Arrange
        touch($this->tempLogsPath . '/test1.log');
        touch($this->tempLogsPath . '/test2.log');
        touch($this->tempLogsPath . '/test3.log.gz');

        // Act
        $files = $this->service->listAllFiles();

        // Assert
        $this->assertIsArray($files);
        $this->assertArrayHasKey('extracted', $files);
        $this->assertArrayHasKey('compressed', $files);
        $this->assertCount(2, $files['extracted']);
        $this->assertCount(1, $files['compressed']);
    }
}
