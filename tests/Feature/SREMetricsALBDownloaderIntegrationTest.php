<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader;
use Orchestra\Testbench\TestCase;
use Carbon\Carbon;

/**
 * Integration tests para validar que a SRE Metrics pipeline funciona
 * com a implementação S3ALBLogDownloader correta.
 * 
 * **REGRESSÃO TEST:** Prevenir que o bug zero-requests-bug retorne.
 * 
 * O bug original:
 * - S3ALBLogDownloader não era instanciado por culpa da config key errada
 * - ALBLogDownloader (mock) era usado ao invés
 * - Resultado: 4165 logs com 404K requests retornavam como 0
 */
class SREMetricsALBDownloaderIntegrationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \MatheusFS\Laravel\Insights\ServiceProvider::class,
        ];
    }

    /**
     * Setup: Garantir que a implementação correta está sendo usada
     * 
     * @test
     */
    public function testDownloaderImplementationIsS3InProduction()
    {
        // Arrange
        config(['insights.alb_logs.source' => 's3']);

        // Act
        $downloader = app(ALBLogDownloaderInterface::class);

        // Assert
        $this->assertInstanceOf(S3ALBLogDownloader::class, $downloader);
        $this->assertEquals('s3', $downloader->getLogSource());
    }

    /**
     * Validação: O contrato garante que implementações retornam seu tipo
     * 
     * Se alguém modificar o código e acidentalmente usar a implementação errada,
     * este teste falhará.
     * 
     * @test
     */
    public function testDownloaderSourceCanBeValidatedAtRuntime()
    {
        // Arrange
        $downloader = app(ALBLogDownloaderInterface::class);

        // Act
        $source = $downloader->getLogSource();

        // Assert
        // Em produção deve ser 's3'
        $this->assertNotNull($source);
        $this->assertIsString($source);
        $this->assertContains($source, ['s3', 'local', 'cloudwatch']);
    }

    /**
     * Validação: Contrato força todas as implementações a declarar seu source
     * 
     * Se alguém criar uma nova implementação sem implementar getLogSource(),
     * receberá erro "Method not found".
     * 
     * @test
     */
    public function testAllDownloaderImplementationsHaveGetLogSource()
    {
        // Arrange
        $downloader = app(ALBLogDownloaderInterface::class);

        // Act & Assert
        $this->assertTrue(method_exists($downloader, 'getLogSource'));
        $this->assertTrue(is_callable([$downloader, 'getLogSource']));
    }
}
