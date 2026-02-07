<?php

namespace Tests\Unit;

use MatheusFS\Laravel\Insights\Services\Domain\ALBLogAnalyzer;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

/**
 * Teste crítico: Garante que ALBLogAnalyzer detecta erros 5xx corretamente
 * 
 * Este teste previne o bug onde o analyzer usava 'status_code' em vez de 'target_status_code',
 * causando contagem zero de erros mesmo quando logs continham 5xx.
 */
class ALBLogAnalyzerErrorDetectionTest extends TestCase
{
    private ALBLogAnalyzer $analyzer;
    private LogParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LogParserService();
        $this->analyzer = new ALBLogAnalyzer($this->parser);
    }

    /**
     * @test
     * Verifica que o analyzer conta erros 5xx quando logs reais do parser são fornecidos
     */
    public function it_counts_5xx_errors_from_parsed_logs(): void
    {
        // Log ALB real com erro 502 Bad Gateway
        $log_line_with_502 = 'https 2026-02-02T22:30:15.042242Z app/production/6bed1cf9aa718eab 203.0.113.42:50902 172.31.19.169:8000 0.000 1.944 0.000 502 502 289 1462 "GET https://api.example.com:443/api/users HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc "Root=1-697fe7da-497ec04f652c35775dfd1e00" "api.example.com" "arn:aws:acm:us-east-1:123456789:certificate/abc" 0 2026-02-02T22:30:13.098000Z "forward" "-" "-" "172.31.19.169:8000" "502" "-" "-" TID_abc "-" "-" "-"';
        
        // Log normal com sucesso 200
        $log_line_with_200 = 'https 2026-02-02T22:30:16.042242Z app/production/6bed1cf9aa718eab 203.0.113.43:50903 172.31.19.169:8000 0.000 0.050 0.000 200 200 300 1500 "GET https://api.example.com:443/api/products HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc "Root=1-697fe7db-497ec04f652c35775dfd1e01" "api.example.com" "arn:aws:acm:us-east-1:123456789:certificate/abc" 0 2026-02-02T22:30:15.098000Z "forward" "-" "-" "172.31.19.169:8000" "200" "-" "-" TID_def "-" "-" "-"';

        // Log com erro 503 Service Unavailable
        $log_line_with_503 = 'https 2026-02-02T22:30:17.042242Z app/production/6bed1cf9aa718eab 203.0.113.44:50904 172.31.19.169:8000 0.000 30.000 0.000 503 503 250 1400 "POST https://api.example.com:443/api/orders HTTP/1.1" "curl/7.68.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc "Root=1-697fe7dc-497ec04f652c35775dfd1e02" "api.example.com" "arn:aws:acm:us-east-1:123456789:certificate/abc" 0 2026-02-02T22:30:16.098000Z "forward" "-" "-" "172.31.19.169:8000" "503" "-" "-" TID_ghi "-" "-" "-"';

        // Parse logs usando o mesmo parser que o sistema usa
        $parsed_logs = [];
        foreach ([$log_line_with_502, $log_line_with_200, $log_line_with_503] as $line) {
            $parsed = $this->parser->parseLogLine($line);
            $this->assertNotNull($parsed, "Parser should successfully parse log line");
            $parsed_logs[] = $parsed;
        }

        // Analisar logs
        $result = $this->analyzer->analyze($parsed_logs, Carbon::parse('2026-02-02'));

        // ASSERÇÕES CRÍTICAS: Se esse teste falhar, erro 5xx não está sendo detectado
        $this->assertArrayHasKey('by_request_type', $result);
        $this->assertArrayHasKey('API', $result['by_request_type']);
        $this->assertArrayHasKey('BOT', $result['by_request_type']);
        
        $api_metrics = $result['by_request_type']['API'];
        $bot_metrics = $result['by_request_type']['BOT'];
        
        // Total de requests API: 2 (1 sucesso + 1 erro)
        $this->assertEquals(2, $api_metrics['total_requests'], 
            'Should count 2 API requests (502 error + 200 success)');
        
        // Total de requests BOT: 1 (curl/7.68.0 é classificado como BOT)
        $this->assertEquals(1, $bot_metrics['total_requests'],
            'Should count 1 BOT request (curl user-agent)');
        
        // Erros 5xx API: 1 (502)
        $this->assertEquals(1, $api_metrics['errors_5xx'], 
            'CRITICAL: Should detect 502 error in API requests. If this fails, analyzer is not reading target_status_code correctly!');
        
        // Erros 5xx BOT: 1 (503)
        $this->assertEquals(1, $bot_metrics['errors_5xx'],
            'CRITICAL: Should detect 503 error in BOT requests. If this fails, analyzer is not reading target_status_code correctly!');
        
        // Taxa de erro API: 1 erro em 2 requests = 50%
        $error_rate_api = ($api_metrics['errors_5xx'] / $api_metrics['total_requests']) * 100;
        $this->assertEqualsWithDelta(50.0, $error_rate_api, 1.0, 
            'Error rate should be 50% (1 error in 2 API requests)');
        
        // Taxa de erro BOT: 1 erro em 1 request = 100%
        $error_rate_bot = ($bot_metrics['errors_5xx'] / $bot_metrics['total_requests']) * 100;
        $this->assertEqualsWithDelta(100.0, $error_rate_bot, 1.0,
            'Error rate should be 100% (1 error in 1 BOT request)');
    }

    /**
     * @test
     * Verifica que parser retorna os campos corretos que analyzer espera
     */
    public function parser_output_has_required_fields_for_analyzer(): void
    {
        $sample_log = 'https 2026-02-02T22:30:15.042242Z app/production/6bed1cf9aa718eab 203.0.113.42:50902 172.31.19.169:8000 0.000 1.944 0.000 502 502 289 1462 "GET https://api.example.com:443/api/users HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc "Root=1-test" "api.example.com" "arn:aws:acm:us-east-1:123456789:certificate/abc" 0 2026-02-02T22:30:13.098000Z "forward" "-" "-" "172.31.19.169:8000" "502" "-" "-" TID_abc "-" "-" "-"';
        
        $parsed = $this->parser->parseLogLine($sample_log);
        
        $this->assertNotNull($parsed);
        
        // CAMPO CRÍTICO: target_status_code deve existir
        $this->assertArrayHasKey('target_status_code', $parsed, 
            'Parser MUST provide target_status_code for analyzer to detect errors');
        
        // Outros campos necessários
        $this->assertArrayHasKey('path', $parsed);
        $this->assertArrayHasKey('user_agent', $parsed);
        $this->assertArrayHasKey('request_type', $parsed);
        
        // Verificar que target_status_code é numérico
        $this->assertIsInt($parsed['target_status_code']);
        $this->assertEquals(502, $parsed['target_status_code']);
        
        // Verificar que elb_status_code também existe (mas analyzer não deve usá-lo para contar erros)
        $this->assertArrayHasKey('elb_status_code', $parsed);
    }

    /**
     * @test
     * Verifica comportamento quando não há erros
     */
    public function it_returns_zero_errors_when_all_requests_succeed(): void
    {
        $success_log = 'https 2026-02-02T22:30:16.042242Z app/production/6bed1cf9aa718eab 203.0.113.43:50903 172.31.19.169:8000 0.000 0.050 0.000 200 200 300 1500 "GET https://api.example.com:443/api/products HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc "Root=1-test" "api.example.com" "arn:aws:acm:us-east-1:123456789:certificate/abc" 0 2026-02-02T22:30:15.098000Z "forward" "-" "-" "172.31.19.169:8000" "200" "-" "-" TID_def "-" "-" "-"';
        
        $parsed = $this->parser->parseLogLine($success_log);
        $result = $this->analyzer->analyze([$parsed], Carbon::parse('2026-02-02'));
        
        $api_metrics = $result['by_request_type']['API'];
        
        $this->assertEquals(1, $api_metrics['total_requests']);
        $this->assertEquals(0, $api_metrics['errors_5xx'], 
            'Should have zero 5xx errors when all requests return 200');
    }

    /**
     * @test
     * Verifica que diferentes códigos 5xx são detectados (500, 501, 502, 503, 504, etc)
     */
    public function it_detects_all_5xx_status_codes(): void
    {
        $error_codes = [500, 501, 502, 503, 504, 505, 599];
        $parsed_logs = [];
        
        foreach ($error_codes as $code) {
            $log = "https 2026-02-02T22:30:16.042242Z app/production/6bed1cf9aa718eab 203.0.113.43:50903 172.31.19.169:8000 0.000 0.050 0.000 {$code} {$code} 300 1500 \"GET https://api.example.com:443/api/test HTTP/1.1\" \"Mozilla/5.0\" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:123456789:targetgroup/prod/abc \"Root=1-test\" \"api.example.com\" \"arn:aws:acm:us-east-1:123456789:certificate/abc\" 0 2026-02-02T22:30:15.098000Z \"forward\" \"-\" \"-\" \"172.31.19.169:8000\" \"{$code}\" \"-\" \"-\" TID_test \"-\" \"-\" \"-\"";
            
            $parsed = $this->parser->parseLogLine($log);
            if ($parsed) {
                $parsed_logs[] = $parsed;
            }
        }
        
        $result = $this->analyzer->analyze($parsed_logs, Carbon::parse('2026-02-02'));
        $api_metrics = $result['by_request_type']['API'];
        
        $this->assertEquals(count($error_codes), $api_metrics['errors_5xx'],
            'Should detect all 5xx error codes (500-599)');
    }
}
