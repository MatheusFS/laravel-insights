<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Domain;

use MatheusFS\Laravel\Insights\Services\Domain\ALBLogDownloader;
use MatheusFS\Laravel\Insights\Services\Domain\ALBLogAnalyzer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use MatheusFS\Laravel\Insights\InsightsServiceProvider;

/**
 * Test: ALBLogDownloader (Mock/Local Implementation)
 * 
 * Validar que:
 * 1. Usa configuração sre_metrics_path corretamente
 * 2. Salva JSON calculados no diretório correto
 * 3. Retorna estrutura vazia quando não há dados
 * 4. Implementa interface ALBLogDownloaderInterface corretamente
 */
class ALBLogDownloaderTest extends TestCase
{
    private ALBLogDownloader $downloader;
    private string $test_storage_path;

    protected function getPackageProviders($app)
    {
        return [InsightsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->test_storage_path = storage_path('app/test-sre-metrics');
        
        // Configure test path
        config()->set('insights.sre_metrics_path', $this->test_storage_path);

        // Mock ALBLogAnalyzer
        $analyzer = $this->createMock(ALBLogAnalyzer::class);
        $analyzer->method('analyze')->willReturn([
            'by_request_type' => [
                'API' => ['total_requests' => 100, 'errors_5xx' => 5],
                'UI' => ['total_requests' => 200, 'errors_5xx' => 2],
                'BOT' => ['total_requests' => 50, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 300, 'errors_5xx' => 1],
            ],
        ]);

        $this->downloader = new ALBLogDownloader($analyzer, $this->test_storage_path);
    }

    protected function tearDown(): void
    {
        // Limpar diretório de teste
        if (File::isDirectory($this->test_storage_path)) {
            File::deleteDirectory($this->test_storage_path);
        }

        parent::tearDown();
    }

    /**
     * @test
     */
    public function uses_configured_sre_metrics_path(): void
    {
        // Assert
        $this->assertEquals($this->test_storage_path, $this->downloader->getStoragePath());
    }

    /**
     * @test
     */
    public function uses_default_path_when_config_not_set(): void
    {
        // Arrange: Criar downloader com config retornando string vazia
        Config::clearResolvedInstances();
        Config::shouldReceive('get')
            ->with('insights.sre_metrics_path', \Mockery::any())
            ->once()
            ->andReturn('');
        
        $analyzer = $this->createMock(ALBLogAnalyzer::class);
        
        // Act
        $downloader = new ALBLogDownloader($analyzer);

        // Assert: Deve usar o fallback storage_path('insights/reliability/sre-metrics')
        $reflection = new \ReflectionClass($downloader);
        $property = $reflection->getProperty('storage_path');
        $property->setAccessible(true);
        
        $this->assertEquals(
            storage_path('insights/reliability/sre-metrics'),
            $property->getValue($downloader)
        );
    }

    /**
     * @test
     */
    public function creates_monthly_directory_structure(): void
    {
        // Arrange
        $date = Carbon::parse('2026-02-06');

        // Act
        $this->downloader->downloadForDate($date);

        // Assert
        $expected_dir = $this->test_storage_path . '/2026-02';
        $this->assertDirectoryExists($expected_dir);
    }

    /**
     * @test
     */
    public function saves_daily_json_file(): void
    {
        // Arrange
        $date = Carbon::parse('2026-02-06');

        // Act
        $this->downloader->downloadForDate($date);

        // Assert
        $expected_file = $this->test_storage_path . '/2026-02/2026-02-06.json';
        $this->assertFileExists($expected_file);
        
        $content = json_decode(File::get($expected_file), true);
        $this->assertArrayHasKey('by_request_type', $content);
        $this->assertArrayHasKey('API', $content['by_request_type']);
    }

    /**
     * @test
     */
    public function returns_cached_data_without_force_option(): void
    {
        // Arrange
        $date = Carbon::parse('2026-02-06');
        
        // First download
        $result1 = $this->downloader->downloadForDate($date);

        // Act: Second download without force
        $result2 = $this->downloader->downloadForDate($date);

        // Assert: Should return same data from cache
        $this->assertEquals($result1, $result2);
    }

    /**
     * @test
     */
    public function forces_redownload_with_force_option(): void
    {
        // Arrange
        $date = Carbon::parse('2026-02-06');
        $day_file = $this->test_storage_path . '/2026-02/2026-02-06.json';
        
        // First download
        $this->downloader->downloadForDate($date);
        $original_time = File::lastModified($day_file);
        
        // Wait a bit to ensure different timestamp
        sleep(1);

        // Act: Force redownload
        $this->downloader->downloadForDate($date, ['force' => true]);

        // Assert: File should have been regenerated
        $new_time = File::lastModified($day_file);
        $this->assertNotEquals($original_time, $new_time);
    }

    /**
     * @test
     */
    public function aggregates_monthly_data_correctly(): void
    {
        // Arrange
        $month = '2026-02';
        $date1 = Carbon::parse('2026-02-01');
        $date2 = Carbon::parse('2026-02-02');

        // Download data for 2 days
        $this->downloader->downloadForDate($date1);
        $this->downloader->downloadForDate($date2);

        // Act
        $aggregate = $this->downloader->downloadForMonth($month);

        // Assert
        $this->assertArrayHasKey('by_request_type', $aggregate);
        $this->assertArrayHasKey('period', $aggregate);
        
        // Should aggregate across all days
        $this->assertGreaterThan(0, $aggregate['by_request_type']['API']['total_requests']);
    }

    /**
     * @test
     */
    public function saves_monthly_aggregate_file(): void
    {
        // Arrange
        $month = '2026-02';

        // Act
        $this->downloader->downloadForMonth($month);

        // Assert
        $aggregate_file = $this->test_storage_path . '/2026-02/monthly_aggregate.json';
        $this->assertFileExists($aggregate_file);
        
        $content = json_decode(File::get($aggregate_file), true);
        $this->assertArrayHasKey('period', $content);
        $this->assertArrayHasKey('timestamp', $content);
    }

    /**
     * @test
     */
    public function checks_data_existence_correctly(): void
    {
        // Arrange
        $date = Carbon::parse('2026-02-06');

        // Act & Assert: Before download
        $this->assertFalse($this->downloader->hasDataForDate($date));

        // Download data
        $this->downloader->downloadForDate($date);

        // Act & Assert: After download
        $this->assertTrue($this->downloader->hasDataForDate($date));
    }
}
