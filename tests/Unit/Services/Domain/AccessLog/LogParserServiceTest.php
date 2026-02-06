<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Domain\AccessLog;

use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use PHPUnit\Framework\TestCase;

class LogParserServiceTest extends TestCase
{
    private LogParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LogParserService;
    }

    public function test_classifies_api_request_correctly(): void
    {
        $result = $this->parser->classifyRequestType('/api/briefings/123');

        $this->assertEquals('API', $result);
    }

    public function test_classifies_assets_request_correctly(): void
    {
        $paths = [
            '/assets/app.js',
            '/css/style.css',
            '/images/logo.png',
            '/fonts/roboto.woff2',
            '/favicon.ico',
        ];

        foreach ($paths as $path) {
            $result = $this->parser->classifyRequestType($path);
            $this->assertEquals('ASSETS', $result, "Failed for path: {$path}");
        }
    }

    public function test_classifies_assets_by_file_extension(): void
    {
        // Test files with extensions that can be anywhere
        $paths = [
            '/download/style.css',
            '/docs/manual.pdf',
            '/public/logo.svg',
            '/uploads/image.jpg',
            '/static/script.js',
            '/app/theme.scss',
            '/data/archive.zip',
            '/media/video.mp4',
            '/libs/font.woff2',
        ];

        foreach ($paths as $path) {
            $result = $this->parser->classifyRequestType($path);
            $this->assertEquals('ASSETS', $result, "Failed for path: {$path}");
        }
    }

    public function test_classifies_ui_request_correctly(): void
    {
        $paths = [
            '/dashboard',
            '/briefings',
            '/',
            '/profile/settings',
        ];

        foreach ($paths as $path) {
            $result = $this->parser->classifyRequestType($path);
            $this->assertEquals('UI', $result, "Failed for path: {$path}");
        }
    }

    public function test_parses_valid_alb_log_line(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 200 200 1234 5678 "GET /api/briefings HTTP/1.1" "Mozilla/5.0 (Windows NT 10.0; Win64; x64)" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertNotNull($result);
        $this->assertEquals('192.168.1.100', $result['client_ip']);
        $this->assertEquals(54321, $result['client_port']);
        $this->assertEquals(200, $result['elb_status_code']);
        $this->assertEquals(200, $result['target_status_code']);
        $this->assertEquals('GET', $result['method']);
        $this->assertEquals('/api/briefings', $result['path']);
        $this->assertEquals('HTTP/1.1', $result['protocol']);
        $this->assertEquals('API', $result['request_type']);
    }

    public function test_parses_log_line_with_error_status(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 502 502 1234 0 "GET /api/projects HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertNotNull($result);
        $this->assertEquals(502, $result['elb_status_code']);
        $this->assertEquals(502, $result['target_status_code']);
    }

    public function test_returns_null_for_invalid_log_line(): void
    {
        $invalidLines = [
            '',
            'invalid log format',
            'partial log 2026-02-03',
        ];

        foreach ($invalidLines as $line) {
            $result = $this->parser->parseLogLine($line);
            $this->assertNull($result, "Should return null for: {$line}");
        }
    }

    public function test_parses_multiple_log_lines(): void
    {
        $lines = [
            'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 200 200 1234 5678 "GET /api/briefings HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2',
            'https 2026-02-03T10:16:33.123456Z app/refresher-alb/abc123 192.168.1.101:54322 10.0.1.50:80 0.002 0.060 0.000 404 404 1234 5678 "GET /not-found HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2',
            'invalid line',
        ];

        $results = $this->parser->parseLogLines($lines);

        $this->assertCount(2, $results); // Only 2 valid lines
        $this->assertEquals('192.168.1.100', $results[0]['client_ip']);
        $this->assertEquals('192.168.1.101', $results[1]['client_ip']);
    }

    public function test_parses_log_line_with_no_target(): void
    {
        // ALB pode não ter target se request falhou antes de alcançar backend
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 - 0.000 -1 0.000 503 - 1234 0 "GET /api/briefings HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertNotNull($result);
        $this->assertEquals('192.168.1.100', $result['client_ip']);
        $this->assertNull($result['target_ip']);
        $this->assertNull($result['target_port']);
    }

    public function test_extracts_processing_times_correctly(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.123 0.456 0.789 200 200 1234 5678 "GET /api/briefings HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertEquals(0.123, $result['request_processing_time']);
        $this->assertEquals(0.456, $result['target_processing_time']);
        $this->assertEquals(0.789, $result['response_processing_time']);
    }

    public function test_extracts_byte_counts_correctly(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 200 200 12345 67890 "GET /api/briefings HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertEquals(12345, $result['received_bytes']);
        $this->assertEquals(67890, $result['sent_bytes']);
    }

    public function test_classifies_bot_request_correctly(): void
    {
        $botUserAgents = [
            'Mozilla/5.0 (compatible; Googlebot/2.1)',
            'Mozilla/5.0 (compatible; bingbot/2.0)',
            'Mozilla/5.0 (compatible; Uptimerobot/2.0)',
            'python-requests/2.28.1',
            'curl/7.85.0',
        ];

        foreach ($botUserAgents as $userAgent) {
            $result = $this->parser->classifyRequestType('/dashboard', $userAgent);
            $this->assertEquals('BOT', $result, "Failed for user-agent: {$userAgent}");
        }
    }

    public function test_parses_bot_request_correctly(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 200 200 1234 5678 "GET /robots.txt HTTP/1.1" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertNotNull($result);
        $this->assertEquals('BOT', $result['request_type']);
        $this->assertEquals('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', $result['user_agent']);
    }

    public function test_human_request_not_classified_as_bot(): void
    {
        $humanUserAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)',
            'Mozilla/5.0 (Linux; Android 12)',
        ];

        foreach ($humanUserAgents as $userAgent) {
            $result = $this->parser->classifyRequestType('/dashboard', $userAgent);
            $this->assertEquals('UI', $result, "Failed for user-agent: {$userAgent}");
        }
    }

    public function test_classifies_malicious_request_as_bot(): void
    {
        $maliciousPaths = [
            '/wp-admin',
            '/wp-login.php',
            '/xmlrpc.php',
            '/.env',
            '/.git',
            '/.svn',
            '/.htaccess',
            '/phpmyadmin',
            '/shell.php',
            '/adminer.php',
            '/../etc/passwd',
            '/..\\windows\\system32',
            '/phpinfo.php',
            '_ignition/execute-solution',
        ];

        foreach ($maliciousPaths as $path) {
            $result = $this->parser->classifyRequestType($path);
            $this->assertEquals('BOT', $result, "Failed for path: {$path}");
        }
    }

    public function test_parses_malicious_request_correctly(): void
    {
        $line = 'https 2026-02-03T10:15:32.123456Z app/refresher-alb/abc123 192.168.1.100:54321 10.0.1.50:80 0.001 0.050 0.000 404 404 1234 5678 "GET /.env HTTP/1.1" "curl/7.85.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2';

        $result = $this->parser->parseLogLine($line);

        $this->assertNotNull($result);
        $this->assertEquals('BOT', $result['request_type']);
        $this->assertEquals('/.env', $result['path']);
    }
}
