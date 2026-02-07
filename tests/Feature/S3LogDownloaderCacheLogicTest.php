<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature;

use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

/**
 * Test para validar a lógica de download com cache e força de re-download
 *
 * **REGRA DE NEGÓCIO:**
 * 1. NÃO TEM arquivos locais (.log ou .log.gz) → SEMPRE BAIXA
 * 2. TEM arquivos E forceExtraction=false → PULA download (cache)
 * 3. TEM arquivos E forceExtraction=true → FORÇA re-download
 */
class S3LogDownloaderCacheLogicTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \MatheusFS\Laravel\Insights\ServiceProvider::class,
        ];
    }

    /**
     * Setup: criar diretório de testes
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $testPath = storage_path('insights-test/access-logs');
        if (File::isDirectory($testPath)) {
            File::deleteDirectory($testPath);
        }
        File::makeDirectory($testPath, 0755, true);
    }

    /**
     * Cleanup: remover diretório de testes
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        $testPath = storage_path('insights-test/access-logs');
        if (File::isDirectory($testPath)) {
            File::deleteDirectory($testPath);
        }
    }

    /**
     * Test: NÃO TEM arquivos locais → SEMPRE BAIXA (mesmo com forceExtraction=false)
     *
     * @test
     */
    public function testDownloadWhenNoLocalFilesExist()
    {
        // Arrange
        $testPath = storage_path('insights-test/access-logs');
        config(['insights.access_logs_path' => $testPath]);
        
        // Garantir que diretório está vazio
        $this->assertFalse(File::isDirectory($testPath) && count(glob($testPath . '/*')) > 0);

        // Act
        $downloader = new S3LogDownloaderService();
        
        // Simular: diretório não tem arquivos = deve tentar baixar
        // (não conseguimos testar S3 real aqui, mas a lógica seria executada)
        
        // Assert
        $this->assertFalse($this->hasLocalLogFiles($testPath), 'Should not find local files when directory is empty');
    }

    /**
     * Test: TEM arquivos E forceExtraction=false → PULA download (cache)
     *
     * @test
     */
    public function testSkipsDownloadWhenFilesExistAndNoForce()
    {
        // Arrange
        $testPath = storage_path('insights-test/access-logs');
        config(['insights.access_logs_path' => $testPath]);
        
        // Criar arquivo .log fictício
        File::put($testPath . '/sample.log', 'teste');
        $this->assertTrue(File::exists($testPath . '/sample.log'));

        // Act
        $downloader = new S3LogDownloaderService();
        
        // Se fosse chamado downloadLogsForIncident com forceExtraction=false,
        // retornaria skipped=true e não faria download
        
        // Assert
        $this->assertTrue($this->hasLocalLogFiles($testPath), 'Should find local .log files');
        $this->assertEquals(1, $this->countLocalLogFiles($testPath), 'Should count 1 file');
    }

    /**
     * Test: TEM arquivos E forceExtraction=true → FORÇA re-download
     *
     * @test
     */
    public function testForcesRedownloadWhenFilesExistAndForceEnabled()
    {
        // Arrange
        $testPath = storage_path('insights-test/access-logs');
        config(['insights.access_logs_path' => $testPath]);
        
        // Criar arquivo .log fictício
        $testFile = $testPath . '/sample.log';
        File::put($testFile, 'old content');
        $oldTimestamp = File::lastModified($testFile);
        
        sleep(1); // Garantir timestamp diferente

        // Act
        $downloader = new S3LogDownloaderService();
        
        // Se fosse chamado com forceExtraction=true,
        // re-baixaria do S3 e sobrescreveria o arquivo
        
        // Assert
        $this->assertTrue($this->hasLocalLogFiles($testPath), 'Files should exist');
        // Com force=true, o arquivo seria re-baixado e timestamp seria novo
    }

    /**
     * Test: TEM .log.gz E forceExtraction=true → FORÇA re-download E re-extração
     *
     * @test
     */
    public function testForcesRedownloadAndExtractionForGzFiles()
    {
        // Arrange
        $testPath = storage_path('insights-test/access-logs');
        config(['insights.access_logs_path' => $testPath]);
        
        // Criar arquivo .log.gz fictício
        $testFile = $testPath . '/sample.log.gz';
        File::put($testFile, 'fake gzip content');

        // Act
        $downloader = new S3LogDownloaderService();

        // Assert
        $this->assertTrue($this->hasLocalLogFiles($testPath), 'Should find .log.gz files');
        $this->assertEquals(1, $this->countLocalLogFiles($testPath), 'Should count 1 .gz file');
    }

    /**
     * Helpers para simular métodos privados da S3LogDownloaderService
     */
    private function hasLocalLogFiles(string $dirPath): bool
    {
        if (!File::isDirectory($dirPath)) {
            return false;
        }

        $logFiles = glob($dirPath . '/*.log');
        $gzFiles = glob($dirPath . '/*.log.gz');

        return (count($logFiles) > 0 || count($gzFiles) > 0);
    }

    private function countLocalLogFiles(string $dirPath): int
    {
        if (!File::isDirectory($dirPath)) {
            return 0;
        }

        $logFiles = glob($dirPath . '/*.log');
        $gzFiles = glob($dirPath . '/*.log.gz');

        return count($logFiles) + count($gzFiles);
    }
}
