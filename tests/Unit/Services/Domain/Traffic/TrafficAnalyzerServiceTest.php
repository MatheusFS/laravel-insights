<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Domain\Traffic;

use MatheusFS\Laravel\Insights\Services\Domain\Traffic\TrafficAnalyzerService;
use PHPUnit\Framework\TestCase;

class TrafficAnalyzerServiceTest extends TestCase
{
    private TrafficAnalyzerService $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new TrafficAnalyzerService;
    }

    public function test_analyzes_traffic_by_request_type(): void
    {
        $records = [
            // API requests
            ['client_ip' => '192.168.1.1', 'path' => '/api/briefings', 'request_type' => 'API', 'target_status_code' => 200],
            ['client_ip' => '192.168.1.2', 'path' => '/api/projects', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.3', 'path' => '/api/users', 'request_type' => 'API', 'target_status_code' => 404],
            // UI requests
            ['client_ip' => '192.168.1.4', 'path' => '/dashboard', 'request_type' => 'UI', 'target_status_code' => 200],
            ['client_ip' => '192.168.1.5', 'path' => '/briefings', 'request_type' => 'UI', 'target_status_code' => 200],
            // ASSETS requests
            ['client_ip' => '192.168.1.6', 'path' => '/app.js', 'request_type' => 'ASSETS', 'target_status_code' => 200],
        ];

        $result = $this->analyzer->analyzeByRequestType($records);

        // Verificar estrutura
        $this->assertArrayHasKey('API', $result);
        $this->assertArrayHasKey('UI', $result);
        $this->assertArrayHasKey('ASSETS', $result);

        // Verificar API
        $this->assertEquals(3, $result['API']['total_requests']);
        $this->assertEquals(2, $result['API']['total_errors']); // 500 + 404
        $this->assertEquals(1, $result['API']['errors_5xx']);
        $this->assertEquals(1, $result['API']['errors_4xx']);
        $this->assertEquals(66.67, $result['API']['error_rate']); // 2/3 * 100

        // Verificar UI
        $this->assertEquals(2, $result['UI']['total_requests']);
        $this->assertEquals(0, $result['UI']['total_errors']);
        $this->assertEquals(0.0, $result['UI']['error_rate']);

        // Verificar ASSETS
        $this->assertEquals(1, $result['ASSETS']['total_requests']);
        $this->assertEquals(0, $result['ASSETS']['total_errors']);
    }

    public function test_tracks_unique_ips_with_errors(): void
    {
        $records = [
            ['client_ip' => '192.168.1.1', 'path' => '/api/test', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.1', 'path' => '/api/test', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.2', 'path' => '/api/test', 'request_type' => 'API', 'target_status_code' => 500],
        ];

        $result = $this->analyzer->analyzeByRequestType($records);

        $this->assertEquals(2, $result['API']['unique_ips_with_errors']); // 2 IPs distintos
    }

    public function test_tracks_top_error_paths(): void
    {
        $records = [
            ['client_ip' => '192.168.1.1', 'path' => '/api/error1', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.1', 'path' => '/api/error1', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.1', 'path' => '/api/error1', 'request_type' => 'API', 'target_status_code' => 500],
            ['client_ip' => '192.168.1.2', 'path' => '/api/error2', 'request_type' => 'API', 'target_status_code' => 404],
            ['client_ip' => '192.168.1.3', 'path' => '/api/error2', 'request_type' => 'API', 'target_status_code' => 404],
        ];

        $result = $this->analyzer->analyzeByRequestType($records);

        $topPaths = $result['API']['top_error_paths'];

        $this->assertArrayHasKey('/api/error1', $topPaths);
        $this->assertArrayHasKey('/api/error2', $topPaths);
        $this->assertEquals(3, $topPaths['/api/error1']);
        $this->assertEquals(2, $topPaths['/api/error2']);
    }

    public function test_classifies_ips_as_malicious(): void
    {
        $records = [];

        // IP malicioso: 210 requests com 96% de erro (critério: >= 0.95 && > 200)
        for ($i = 0; $i < 210; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.100',
                'path' => '/api/test',
                'request_type' => 'API',
                'target_status_code' => $i < 202 ? 500 : 200, // 202 erros de 210 = 96%
                'user_agent' => 'BadBot',
            ];
        }

        $result = $this->analyzer->classifyIpsByBehavior($records);

        $this->assertArrayHasKey('malicious', $result);
        $this->assertCount(1, $result['malicious']);
        $this->assertEquals('192.168.1.100', $result['malicious'][0]['ip']);
        $this->assertGreaterThan(95, $result['malicious'][0]['error_rate']);
    }

    public function test_classifies_ips_as_suspicious(): void
    {
        $records = [];

        // IP suspeito: 100 requests com 90% de erro (critério: >= 0.9)
        for ($i = 0; $i < 100; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.200',
                'path' => '/api/test',
                'request_type' => 'API',
                'target_status_code' => $i < 90 ? 500 : 200, // 90 erros de 100 = 90%
                'user_agent' => 'Scanner',
            ];
        }

        $result = $this->analyzer->classifyIpsByBehavior($records);

        $this->assertArrayHasKey('suspicious', $result);
        $this->assertCount(1, $result['suspicious']);
        $this->assertEquals('192.168.1.200', $result['suspicious'][0]['ip']);
    }

    public function test_classifies_ips_as_suspicious_by_path_scanning(): void
    {
        $records = [];

        // IP suspeito: acessa 60 paths diferentes (path scanning)
        for ($i = 0; $i < 60; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.300',
                'path' => "/api/path{$i}",
                'request_type' => 'API',
                'target_status_code' => 404,
                'user_agent' => 'Scanner',
            ];
        }

        $result = $this->analyzer->classifyIpsByBehavior($records);

        $this->assertArrayHasKey('suspicious', $result);
        $this->assertCount(1, $result['suspicious']);
        $this->assertEquals('192.168.1.300', $result['suspicious'][0]['ip']);
        $this->assertEquals(60, $result['suspicious'][0]['unique_paths']);
    }

    public function test_classifies_ips_as_legitimate(): void
    {
        $records = [];

        // IP legítimo: 50 requests com 5% de erro
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.400',
                'path' => '/api/briefings',
                'request_type' => 'API',
                'target_status_code' => $i < 2 ? 404 : 200, // 2 erros de 50 = 4%
                'user_agent' => 'Mozilla/5.0',
            ];
        }

        $result = $this->analyzer->classifyIpsByBehavior($records);

        $this->assertArrayHasKey('legitimate', $result);
        $this->assertCount(1, $result['legitimate']);
        $this->assertEquals('192.168.1.400', $result['legitimate'][0]['ip']);
        $this->assertLessThan(10, $result['legitimate'][0]['error_rate']);
    }

    public function test_classifies_multiple_ips_correctly(): void
    {
        $records = [];

        // Malicious IP: 210 requests com 96% erro (crit\u00e9rio: >= 0.95 && > 200)
        for ($i = 0; $i < 210; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.100',
                'path' => '/api/test',
                'request_type' => 'API',
                'target_status_code' => $i < 202 ? 500 : 200, // 202 erros de 210 = 96%
                'user_agent' => 'BadBot',
            ];
        }

        // Suspicious IP: 100 requests com 90% erro (crit\u00e9rio: >= 0.9)
        for ($i = 0; $i < 100; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.200',
                'path' => '/api/test',
                'request_type' => 'API',
                'target_status_code' => $i < 90 ? 500 : 200, // 90 erros de 100 = 90%
                'user_agent' => 'Scanner',
            ];
        }

        // Legitimate IP: 50 requests com 4% erro
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'client_ip' => '192.168.1.300',
                'path' => '/api/briefings',
                'request_type' => 'API',
                'target_status_code' => $i < 2 ? 404 : 200,
                'user_agent' => 'Mozilla/5.0',
            ];
        }

        $result = $this->analyzer->classifyIpsByBehavior($records);

        $this->assertCount(1, $result['malicious']);
        $this->assertCount(1, $result['suspicious']);
        $this->assertCount(1, $result['legitimate']);
    }

    public function test_handles_empty_records(): void
    {
        $result = $this->analyzer->analyzeByRequestType([]);

        $this->assertEquals(0, $result['API']['total_requests']);
        $this->assertEquals(0, $result['UI']['total_requests']);
        $this->assertEquals(0, $result['ASSETS']['total_requests']);
    }

    public function test_uses_elb_status_code_when_target_not_available(): void
    {
        $records = [
            ['client_ip' => '192.168.1.1', 'path' => '/api/test', 'request_type' => 'API', 'elb_status_code' => 503, 'target_status_code' => null],
        ];

        $result = $this->analyzer->analyzeByRequestType($records);

        $this->assertEquals(1, $result['API']['total_errors']);
        $this->assertEquals(1, $result['API']['errors_5xx']);
    }
}
