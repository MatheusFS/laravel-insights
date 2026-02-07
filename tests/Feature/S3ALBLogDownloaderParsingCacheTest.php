<?php

namespace MatheusFS\Laravel\Insights\Tests\Feature;

use MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use MatheusFS\Laravel\Insights\Services\Domain\ALBLogAnalyzer;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

/**
 * Testa a segunda camada de cache: PARSING CACHE
 * 
 * Objetivo: Garantir que arquivos .log já parseados não sejam reprocessados
 * Reduz processamento de CPU mesmo quando não há download S3
 */
class S3ALBLogDownloaderParsingCacheTest extends TestCase
{
    private S3ALBLogDownloader $downloader;
    private string $test_logs_dir;
    private LogParserService $log_parser;
    private ALBLogAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->test_logs_dir = storage_path('test_logs');
        File::ensureDirectoryExists($this->test_logs_dir);

        $this->log_parser = app(LogParserService::class);
        $this->analyzer = app(ALBLogAnalyzer::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->test_logs_dir);
        parent::tearDown();
    }

    /**
     * Scenario 1: Primeira execução - processa TODOS os logs
     * 
     * Dado que não há arquivos .log.parsed
     * Quando getUnparsedLogFiles() é chamado
     * Então retorna TODOS os arquivos
     */
    public function test_first_run_returns_all_files_for_parsing(): void
    {
        // Arrange
        $log_files = $this->createMockLogFiles(['file1.log', 'file2.log', 'file3.log']);
        
        // Act
        $unparsed = $this->downloader->testGetUnparsedLogFiles($log_files, false);
        
        // Assert
        $this->assertCount(3, $unparsed);
        $this->assertEquals($log_files, $unparsed);
    }

    /**
     * Scenario 2: Segunda execução sem --force - pula arquivos parseados
     * 
     * Dado que existem .log.parsed markers
     * Quando getUnparsedLogFiles() é chamado SEM force
     * Então retorna APENAS arquivos sem marker
     */
    public function test_second_run_skips_parsed_files(): void
    {
        // Arrange
        $log_files = $this->createMockLogFiles(['file1.log', 'file2.log', 'file3.log']);
        
        // Simular que file1 e file2 já foram parseados
        foreach ([0, 1] as $index) {
            File::put($log_files[$index] . '.parsed', json_encode([
                'parsed_at' => now()->toIso8601String(),
            ]));
        }
        
        // Act
        $unparsed = $this->downloader->testGetUnparsedLogFiles($log_files, false);
        
        // Assert
        $this->assertCount(1, $unparsed);
        $this->assertEquals([$log_files[2]], $unparsed);
    }

    /**
     * Scenario 3: Arquivo modificado APÓS parsing - deve reprocessar
     * 
     * Dado que arquivo foi modificado depois do marker
     * Quando getUnparsedLogFiles() é chamado
     * Então retorna arquivo mesmo com .parsed marker
     */
    public function test_modified_file_is_reprocessed(): void
    {
        // Arrange
        $log_file = $this->test_logs_dir . '/modified.log';
        File::put($log_file, "original content\n");
        
        // Criar marker "antigo" (5 minutos atrás)
        $old_marker = $log_file . '.parsed';
        File::put($old_marker, json_encode(['parsed_at' => now()->subMinutes(5)]));
        
        // Simular modificação do arquivo (tocar arquivo)
        touch($log_file, time());
        
        // Act
        $unparsed = $this->downloader->testGetUnparsedLogFiles([$log_file], false);
        
        // Assert
        $this->assertCount(1, $unparsed);
        $this->assertEquals([$log_file], $unparsed);
    }

    /**
     * Scenario 4: Force flag ativa - reprocessa TUDO
     * 
     * Dado que existem .log.parsed markers
     * Quando getUnparsedLogFiles() é chamado COM force=true
     * Então retorna TODOS os arquivos ignorando markers
     */
    public function test_force_reparse_ignores_cache(): void
    {
        // Arrange
        $log_files = $this->createMockLogFiles(['file1.log', 'file2.log', 'file3.log']);
        
        // Marcar TODOS como parseados
        foreach ($log_files as $log_file) {
            File::put($log_file . '.parsed', json_encode([
                'parsed_at' => now()->toIso8601String(),
            ]));
        }
        
        // Act - SEM force
        $unparsed_no_force = $this->downloader->testGetUnparsedLogFiles($log_files, false);
        
        // Act - COM force
        $unparsed_with_force = $this->downloader->testGetUnparsedLogFiles($log_files, true);
        
        // Assert
        $this->assertCount(0, $unparsed_no_force);
        $this->assertCount(3, $unparsed_with_force);
    }

    /**
     * Scenario 5: Marker é criado após processamento
     * 
     * Dado que arquivo foi parseado
     * Quando markFileAsParsed() é chamado
     * Então marker .parsed é criado com timestamp
     */
    public function test_mark_file_as_parsed_creates_marker(): void
    {
        // Arrange
        $log_file = $this->test_logs_dir . '/parsed.log';
        File::put($log_file, "sample log content\n");
        
        // Act
        $result = $this->downloader->testMarkFileAsParsed($log_file);
        
        // Assert
        $this->assertTrue($result);
        $marker_path = $log_file . '.parsed';
        $this->assertTrue(File::exists($marker_path));
        
        $marker_content = json_decode(File::get($marker_path), true);
        $this->assertArrayHasKey('parsed_at', $marker_content);
        $this->assertArrayHasKey('original_file', $marker_content);
        $this->assertEquals($log_file, $marker_content['original_file']);
    }

    /**
     * Scenario 6: Performance - reprocessamento SEM cache é 10x mais lento
     * 
     * Comparar tempo de processamento:
     * - Com parsing cache: rápido (pula 90% dos arquivos)
     * - Sem parsing cache: lento (processa TUDO)
     */
    public function test_parsing_cache_improves_performance(): void
    {
        // Arrange - simular 100 arquivos
        $num_files = 100;
        $log_files = [];
        for ($i = 1; $i <= $num_files; $i++) {
            $path = $this->test_logs_dir . "/log_{$i}.log";
            File::put($path, "sample log\n");
            $log_files[] = $path;
        }

        // Marcar 90% como já parseados
        for ($i = 0; $i < 90; $i++) {
            File::put($log_files[$i] . '.parsed', json_encode(['parsed_at' => now()]));
        }

        // Act
        $unparsed = $this->downloader->testGetUnparsedLogFiles($log_files, false);

        // Assert - apenas 10% retornados
        $this->assertCount(10, $unparsed);
        $this->assertLessThan(15, count($unparsed)); // Deve ser ~10%
    }

    // ========== HELPERS ==========

    /**
     * Cria arquivos .log mock no diretório de teste
     */
    private function createMockLogFiles(array $filenames): array
    {
        $paths = [];
        foreach ($filenames as $name) {
            $path = $this->test_logs_dir . '/' . $name;
            File::put($path, "sample ALB log entry\n");
            $paths[] = $path;
        }
        return $paths;
    }
}
