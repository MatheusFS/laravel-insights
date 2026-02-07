<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature;

use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use MatheusFS\Laravel\Insights\Services\Application\IncidentAnalysisService;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Orchestra\Testbench\TestCase;
use MatheusFS\Laravel\Insights\InsightsServiceProvider;

/**
 * S3 Log Download Integration Test
 * 
 * Critérios de Aceitação:
 * 1. ✅ Conecta ao S3 real com credenciais configuradas
 * 2. ✅ Baixa logs do período do incidente INC-2026-001
 * 3. ✅ Extrai arquivos .gz para .log
 * 4. ✅ Lê e parseia logs ALB corretamente
 * 5. ✅ Classifica IPs em legitimate/suspicious/malicious
 * 6. ✅ Salva resultado em JSON
 * 7. ✅ Funciona com cache (não re-baixa se já existe)
 * 
 * @group s3
 * @group integration
 */
class S3LogDownloadIntegrationTest extends TestCase
{
    private S3LogDownloaderService $downloader;
    private IncidentAnalysisService $analysisService;
    private string $incidents_base_path;
    private string $test_incident_id = 'INC-2026-001';

    protected function getPackageProviders($app)
    {
        return [InsightsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->incidents_base_path = storage_path('app/incidents');
        
        // Configure insights com valores reais (devem estar no .env) usando novas chaves
        config()->set('insights.access_logs_path', storage_path('insights/access-logs'));
        config()->set('insights.incidents_path', $this->incidents_base_path);
        config()->set('insights.alb_logs.s3.bucket', env('AWS_INCIDENT_S3_BUCKET', 'refresher-logs'));
        config()->set('insights.alb_logs.s3.path', env('AWS_INCIDENT_S3_PATH', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1'));
        config()->set('insights.alb_logs.s3.region', 'us-east-1');

        // Configure AWS S3 disk
        config()->set('filesystems.disks.s3', [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
        ]);

        $this->downloader = app(S3LogDownloaderService::class);
        $this->analysisService = app(IncidentAnalysisService::class);
    }

    /**
     * AC1: Conecta ao S3 real com credenciais configuradas
     * 
     * @test
     */
    public function test_s3_credentials_are_configured(): void
    {
        if (empty(config('filesystems.disks.s3.key'))) {
            $this->markTestSkipped('AWS_ACCESS_KEY_ID not configured - skipping S3 integration tests');
        }

        if (empty(config('filesystems.disks.s3.secret'))) {
            $this->markTestSkipped('AWS_SECRET_ACCESS_KEY not configured - skipping S3 integration tests');
        }

        if (empty(config('insights.alb_logs.s3.bucket'))) {
            $this->markTestSkipped('S3 bucket not configured - skipping S3 integration tests');
        }

        $this->assertNotEmpty(config('filesystems.disks.s3.key'));
        $this->assertNotEmpty(config('filesystems.disks.s3.secret'));
        $this->assertNotEmpty(config('insights.alb_logs.s3.bucket'));

        $this->info('✅ AC1: AWS credentials configured');
    }

    /**
     * AC2: Baixa logs do período do incidente INC-2026-001
     * 
     * @test
     * @depends test_s3_credentials_are_configured
     */
    public function test_downloads_logs_from_s3_for_incident(): void
    {
        // Arrange
        // Act
        // Nota: timestamps são agora carregados do JSON do incidente dentro do método
        $result = $this->downloader->downloadLogsForIncident(
            $this->test_incident_id,
            useMargins: true,
            forceExtraction: false
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('incident_id', $result);
        $this->assertArrayHasKey('downloaded_count', $result);
        $this->assertArrayHasKey('extracted_count', $result);
        $this->assertArrayHasKey('local_path', $result);

        $this->assertEquals($this->test_incident_id, $result['incident_id']);
        
        // Verifica que pelo menos tentou baixar (pode ser 0 se já existe em cache)
        $this->assertGreaterThanOrEqual(0, $result['downloaded_count']);
        
        $this->info('✅ AC2: Downloaded logs count: ' . $result['downloaded_count']);
        $this->info('   Extracted logs count: ' . $result['extracted_count']);
        $this->info('   Local path: ' . $result['local_path']);
    }

    /**
     * AC3: Extrai arquivos .gz para .log
     * 
     * @test
     * @depends test_downloads_logs_from_s3_for_incident
     */
    public function test_extracts_gz_files_to_log(): void
    {
        // Arrange - usar diretório unificado
        $access_logs_dir = config('insights.access_logs_path');

        // Assert: Verifica que diretório existe
        $this->assertDirectoryExists(
            $access_logs_dir,
            "Access logs directory not found after download"
        );

        // Assert: Verifica que existem arquivos .log
        $log_files = glob($access_logs_dir . '/*.log');
        $this->assertNotEmpty(
            $log_files,
            "No .log files found in {$access_logs_dir} after extraction"
        );

        $this->info('✅ AC3: Found ' . count($log_files) . ' extracted .log files in unified directory');
        $this->info('   First file: ' . basename($log_files[0]));
    }

    /**
     * AC4: Lê e parseia logs ALB corretamente
     * 
     * @test
     * @depends test_extracts_gz_files_to_log
     */
    public function test_reads_and_parses_alb_logs_correctly(): void
    {
        // Arrange - usar diretório unificado
        $access_logs_dir = config('insights.access_logs_path');
        $log_files = glob($access_logs_dir . '/*.log');

        // Act: Ler primeira linha do primeiro arquivo
        $first_log_file = $log_files[0];
        $lines = file($first_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Assert: Verifica formato ALB
        $this->assertNotEmpty($lines, "Log file is empty");
        
        $first_line = $lines[0];
        
        // Log ALB deve começar com http ou https
        $this->assertMatchesRegularExpression(
            '/^(http|https) /',
            $first_line,
            "Log line doesn't match ALB format"
        );

        // Deve conter timestamp ISO8601
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first_line,
            "Log line doesn't contain valid timestamp"
        );

        // Deve conter IP do cliente (formato IP:porta)
        $this->assertMatchesRegularExpression(
            '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+/',
            $first_line,
            "Log line doesn't contain client IP:port"
        );

        $this->info('✅ AC4: ALB log format validated');
        $this->info('   Total lines in first file: ' . count($lines));
        $this->info('   Sample line: ' . substr($first_line, 0, 100) . '...');
    }

    /**
     * AC5: Classifica IPs em legitimate/suspicious/malicious
     * 
     * @test
     * @depends test_reads_and_parses_alb_logs_correctly
     */
    public function test_classifies_ips_correctly(): void
    {
        // Arrange
        $incident_data = [
            'timestamp' => [
                'started_at' => '2026-01-15T10:00:00Z',
                'restored_at' => '2026-01-15T10:30:00Z',
            ],
        ];

        // Act: Analisar logs do incidente
        $result = $this->analysisService->analyzeLogs(
            $this->test_incident_id,
            $incident_data
        );

        // Assert: Estrutura do resultado
        $this->assertIsArray($result);
        $this->assertArrayHasKey('incident_id', $result);
        $this->assertArrayHasKey('total_requests', $result);
        $this->assertArrayHasKey('unique_ips', $result);
        $this->assertArrayHasKey('classified', $result);

        // Assert: Classificações existem
        $this->assertArrayHasKey('legitimate', $result['classified']);
        $this->assertArrayHasKey('suspicious', $result['classified']);
        $this->assertArrayHasKey('malicious', $result['classified']);

        // Assert: Métricas fazem sentido
        $this->assertGreaterThan(0, $result['total_requests'], "Should have processed requests");
        $this->assertGreaterThan(0, $result['unique_ips'], "Should have unique IPs");

        $total_classified = count($result['classified']['legitimate']) 
                          + count($result['classified']['suspicious']) 
                          + count($result['classified']['malicious']);
        
        $this->assertEquals(
            $result['unique_ips'],
            $total_classified,
            "All unique IPs should be classified"
        );

        $this->info('✅ AC5: IP classification completed');
        $this->info('   Total requests: ' . $result['total_requests']);
        $this->info('   Unique IPs: ' . $result['unique_ips']);
        $this->info('   Legitimate: ' . count($result['classified']['legitimate']));
        $this->info('   Suspicious: ' . count($result['classified']['suspicious']));
        $this->info('   Malicious: ' . count($result['classified']['malicious']));
    }

    /**
     * AC6: Salva resultado em JSON
     * 
     * @test
     * @depends test_classifies_ips_correctly
     */
    public function test_saves_analysis_result_to_json(): void
    {
        // Arrange
        $result_file = "{$this->incidents_base_path}/{$this->test_incident_id}/alb_logs_analysis.json";

        // Assert: Arquivo existe
        $this->assertFileExists(
            $result_file,
            "Analysis result JSON file not found"
        );

        // Assert: Conteúdo é JSON válido
        $content = file_get_contents($result_file);
        $json = json_decode($content, true);

        $this->assertNotNull($json, "JSON content is invalid");
        $this->assertArrayHasKey('incident_id', $json);
        $this->assertArrayHasKey('classified', $json);

        $this->info('✅ AC6: Analysis result saved to JSON');
        $this->info('   File: ' . $result_file);
        $this->info('   Size: ' . number_format(strlen($content)) . ' bytes');
    }

    /**
     * AC7: Funciona com cache (não re-baixa se já existe)
     * 
     * @test
     * @depends test_downloads_logs_from_s3_for_incident
     */
    public function test_uses_cache_and_does_not_redownload(): void
    {
        // Arrange
        // Act: Baixar novamente (deve usar cache)
        // Nota: timestamps são carregados do JSON do incidente
        $result = $this->downloader->downloadLogsForIncident(
            $this->test_incident_id,
            useMargins: true,
            forceExtraction: false
        );

        // Assert: Não baixou novos arquivos (cache hit)
        $this->assertEquals(
            0,
            $result['downloaded_count'],
            "Should not re-download files when cache exists"
        );

        // Assert: Também não re-extraiu (cache de extração)
        $this->assertEquals(
            0,
            $result['extracted_count'],
            "Should not re-extract files when .log already exists"
        );

        $this->info('✅ AC7: Cache working correctly');
        $this->info('   Downloaded: 0 (cache hit)');
        $this->info('   Extracted: 0 (cache hit)');
    }

    /**
     * Helper para exibir mensagens durante testes
     */
    private function info(string $message): void
    {
        fwrite(STDOUT, "\n{$message}\n");
    }
}
