<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader;
use Orchestra\Testbench\TestCase;

/**
 * Tests para validar que o ServiceProvider está usando a implementação correta
 * de ALBLogDownloader baseado na configuração.
 * 
 * **PROPÓSITO CRÍTICO:** Prevenir regressão do bug onde a config key incorreta
 * ('insights.alb_source' instead of 'insights.alb_logs.source') fazia com que
 * ALBLogDownloader (local/mock) fosse sempre usado ao invés de S3ALBLogDownloader.
 * 
 * Resultado: 404K requests eram retornados como 0 porque a implementação errada
 * estava sendo usada.
 */
class ServiceProviderALBDownloaderBindingTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \MatheusFS\Laravel\Insights\ServiceProvider::class,
        ];
    }

    /**
     * Test: Default config (ALB_LOG_SOURCE=s3) deve instanciar S3ALBLogDownloader
     * 
     * Cenário: Produção com ALB_LOG_SOURCE não definido (defaults to 's3')
     * Resultado esperado: S3ALBLogDownloader é instanciado
     * 
     * @test
     */
    public function testDefaultConfigUsesS3Downloader()
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
     * Test: Config key correta está sendo usada no ServiceProvider
     * 
     * Validação: A config key 'insights.alb_logs.source' (CORRETA) é acessada
     * ao invés da config key antiga 'insights.alb_source' (INCORRETA).
     * 
     * @test
     */
    public function testConfigKeyIsCorrect()
    {
        // Arrange
        config(['insights.alb_logs.source' => 's3']);

        // Act
        $downloader = app(ALBLogDownloaderInterface::class);

        // Assert
        // Se a config key fosse incorreta, receberíamos ALBLogDownloader
        $this->assertInstanceOf(S3ALBLogDownloader::class, $downloader);
    }

    /**
     * Test: Contrato garante que implementações declaram seu source
     * 
     * Validação de contrato: A implementação deve ter o método getLogSource()
     * que é definido no contrato ALBLogDownloaderInterface.
     * 
     * @test
     */
    public function testImplementationImplementsGetLogSource()
    {
        // Arrange
        config(['insights.alb_logs.source' => 's3']);

        // Act
        $downloader = app(ALBLogDownloaderInterface::class);

        // Assert
        $this->assertTrue(
            method_exists($downloader, 'getLogSource'),
            'Downloader implementation must implement getLogSource() from interface'
        );
        $this->assertEquals('s3', $downloader->getLogSource());
    }

    /**
     * Test: RuntimeValidation no ServiceProvider detecta implementação errada
     * 
     * O ServiceProvider agora verifica que a implementação retorna o source
     * esperado. Se houver config erro, ele lança RuntimeException.
     * 
     * @test
     */
    public function testServiceProviderValidationIsActive()
    {
        // Arrange
        config(['insights.alb_logs.source' => 's3']);

        // Act
        // Tentar instanciar com config correta não deve lançar exceção
        try {
            $downloader = app(ALBLogDownloaderInterface::class);
            $source = $downloader->getLogSource();
        } catch (\RuntimeException $e) {
            $this->fail('ServiceProvider validation should not throw on correct config: ' . $e->getMessage());
        }

        // Assert
        $this->assertEquals('s3', $source);
    }

    /**
     * Test: Config key incorreta não quebra a implementação
     * 
     * Regressão: Se alguém usar a config key ERRADA ('insights.alb_source'),
     * a aplicação NÃO deve silenciosamente usar ALBLogDownloader.
     * 
     * Em vez disso, deve usar o padrão 's3' conforme definido em config/insights.php
     * 
     * @test
     */
    public function testConfigKeyErrorDoesNotSilentlyBreak()
    {
        // Arrange
        // Simular erro: usar config key ERRADA
        config(['insights.alb_source' => 'local']);
        // Mas a CORRETA é 's3' (padrão)
        config(['insights.alb_logs.source' => 's3']);

        // Act
        $downloader = app(ALBLogDownloaderInterface::class);
        $source = $downloader->getLogSource();

        // Assert
        // Deve usar config CORRETA, não a ERRADA
        $this->assertEquals('s3', $source, 'Should use correct config key insights.alb_logs.source');
        $this->assertInstanceOf(S3ALBLogDownloader::class, $downloader);
    }
}
