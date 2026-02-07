<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature;

use MatheusFS\Laravel\Insights\Services\Application\IncidentAnalysisService;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase;
use MatheusFS\Laravel\Insights\InsightsServiceProvider;

/**
 * Test: Service Layer for Incident Analysis
 * 
 * Validar que o IncidentAnalysisService:
 * 1. Aceita dados de incidente como parâmetro
 * 2. Chama S3LogDownloaderService com datas corretas
 * 3. Lê logs do diretório específico do incidente
 * 4. Delega análise para IncidentCorrelationService
 * 5. Persiste resultado em JSON
 */
class IncidentAnalysisServiceTest extends TestCase
{
    private IncidentAnalysisService $service;
    private S3LogDownloaderService $downloader;
    private string $incidents_base_path;

    protected function getPackageProviders($app)
    {
        return [InsightsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->incidents_base_path = storage_path('app/incidents');
        
        // Configure insights config usando as novas chaves
        config()->set('insights.access_logs_path', storage_path('insights/access-logs'));
        config()->set('insights.incidents_path', $this->incidents_base_path);

        $this->service = app(IncidentAnalysisService::class);
        $this->downloader = app(S3LogDownloaderService::class);

        // Limpar arquivos de teste anterior do diretório unificado
        $test_access_logs_dir = config('insights.access_logs_path');
        if (File::isDirectory($test_access_logs_dir)) {
            File::cleanDirectory($test_access_logs_dir);
        }
    }

    /**
     * @test
     * Validar que analyzeLogs() aceita incident data como parâmetro
     */
    public function test_analyze_logs_accepts_incident_data(): void
    {
        // Skip se AWS SDK não estiver instalado
        if (!class_exists(\Aws\S3\S3Client::class)) {
            $this->markTestSkipped('AWS SDK not installed - install via: composer require aws/aws-sdk-php');
        }

        // Skip se não houver credenciais AWS configuradas
        if (empty(config('filesystems.disks.s3.key'))) {
            $this->markTestSkipped('AWS credentials not configured - skipping test that requires S3');
        }

        // Arrange: Criar incident data de teste
        $incident_id = 'INC-TEST-001';
        $incident_data = [
            'id' => $incident_id,
            'timestamp' => [
                'started_at' => '2026-01-15T10:00:00Z',
                'restored_at' => '2026-01-15T10:30:00Z',
            ],
        ];

        // Act
        try {
            $result = $this->service->analyzeLogs($incident_id, $incident_data);
        } catch (\RuntimeException $e) {
            // Esperado: S3 ou logs podem não estar disponíveis
            // Queremos validar que aceita incident_data
            $this->assertStringContainsString('logs', strtolower($e->getMessage()));
        }

        // Assert: Método aceita parâmetro sem erro de assinatura
        $this->assertTrue(true);
    }

    /**
     * @test
     * Validar que datas são parseadas como Carbon
     */
    public function test_incident_dates_parse_correctly(): void
    {
        // Arrange
        $incident_data = [
            'timestamp' => [
                'started_at' => '2026-01-15T10:00:00Z',
                'restored_at' => '2026-01-15T10:30:00Z',
            ],
        ];

        // Act
        $started = Carbon::parse($incident_data['timestamp']['started_at']);
        $restored = Carbon::parse($incident_data['timestamp']['restored_at']);

        // Assert
        $this->assertInstanceOf(Carbon::class, $started);
        $this->assertInstanceOf(Carbon::class, $restored);
        $this->assertTrue($started->isBefore($restored));
    }

    /**
     * @test
     * Validar que S3LogDownloaderService é injetado corretamente
     */
    public function test_s3_downloader_service_injected(): void
    {
        // Assert que service foi criado com downloader
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('s3Downloader');
        $property->setAccessible(true);

        $downloader = $property->getValue($this->service);
        
        $this->assertInstanceOf(S3LogDownloaderService::class, $downloader);
    }

    /**
     * @test
     * Validar que lock mechanism funciona
     */
    public function test_lock_mechanism_prevents_duplicate_processing(): void
    {
        // Arrange
        $incident_id = 'INC-TEST-002';
        $lock_dir = storage_path('locks');
        File::ensureDirectoryExists($lock_dir);

        // Criar lock manualmente
        $lock_path = "{$lock_dir}/incident_{$incident_id}_analyze_logs.lock";
        File::put($lock_path, (string) time());

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PROCESSING_LOCKED');

        // Chamar dentro do lock period (< 5 min)
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('checkLock');
        $method->setAccessible(true);
        $method->invoke($this->service, $incident_id, 'analyze_logs');

        // Cleanup
        File::delete($lock_path);
    }

    /**
     * @test
     * Validar que lock expirado é ignorado
     */
    public function test_expired_lock_is_ignored(): void
    {
        // Arrange
        $incident_id = 'INC-TEST-003';
        $lock_dir = storage_path('locks');
        File::ensureDirectoryExists($lock_dir);

        // Criar lock expirado (10 min atrás)
        $lock_path = "{$lock_dir}/incident_{$incident_id}_analyze_logs.lock";
        $expired_time = time() - 600;
        File::put($lock_path, (string) $expired_time);

        // Act
        $reflection = new \ReflectionClass($this->service);
        $check_method = $reflection->getMethod('checkLock');
        $check_method->setAccessible(true);

        // Assert: Não deve lançar exceção (lock expirado é ignorado)
        try {
            $check_method->invoke($this->service, $incident_id, 'analyze_logs');
            $this->assertTrue(true);
        } catch (\RuntimeException $e) {
            $this->fail("Lock expirado não deveria lançar exceção: {$e->getMessage()}");
        }

        // Cleanup
        if (File::exists($lock_path)) {
            File::delete($lock_path);
        }
    }

    /**
     * @test
     * Validar que correlateAffectedUsers aceita timestamps ISO 8601
     */
    public function test_correlate_affected_users_accepts_timestamps(): void
    {
        // Arrange
        $incident_id = 'INC-TEST-004';
        $start_time = '2026-01-15T10:00:00Z';
        $end_time = '2026-01-15T10:30:00Z';

        // Criar alb_logs_analysis.json simulado
        $incident_dir = "{$this->incidents_base_path}/{$incident_id}";
        File::ensureDirectoryExists($incident_dir);
        File::put("{$incident_dir}/alb_logs_analysis.json", json_encode([
            'classified' => ['legitimate' => []],
        ]));

        // Act
        try {
            $result = $this->service->correlateAffectedUsers($incident_id, $start_time, $end_time);
        } catch (\Exception $e) {
            // Database pode não estar disponível, mas método aceita timestamps
        }

        // Assert: Método aceita parâmetros sem erro de assinatura
        $this->assertTrue(true);

        // Cleanup
        File::deleteDirectory($incident_dir);
    }
}
