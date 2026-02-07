<?php
/**
 * Test script to verify log download/parsing for incident dates
 * Usage: php test_log_download.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader;
use MatheusFS\Laravel\Insights\Services\Domain\ALBLogAnalyzer;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get service instances
$analyzer = $app->make(ALBLogAnalyzer::class);
$s3_service = $app->make(S3LogDownloaderService::class);
$parser = $app->make(LogParserService::class);

$downloader = new S3ALBLogDownloader($analyzer, $s3_service, $parser);

// Test the incident dates
$dates = [
    Carbon::parse('2026-02-02'),
    Carbon::parse('2026-02-03'),
];

echo "═══════════════════════════════════════════════════════════════\n";
echo "Testing Log Download/Parsing for Incident Dates\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach ($dates as $date) {
    echo "📅 Date: " . $date->format('Y-m-d') . "\n";
    echo "───────────────────────────────────────────────────────────\n";
    
    try {
        // Try downloading with force flag
        $result = $downloader->downloadForDate($date, ['force' => true]);
        
        echo "✅ Download succeeded!\n\n";
        
        // Display results by service
        $services = ['API', 'UI', 'BOT', 'ASSETS'];
        foreach ($services as $service) {
            $data = $result['by_request_type'][$service];
            $requests = $data['total_requests'] ?? 0;
            $errors = $data['errors_5xx'] ?? 0;
            $error_rate = $requests > 0 ? round(($errors / $requests) * 100, 2) : 0;
            
            echo "  $service:\n";
            echo "    - Total requests: $requests\n";
            echo "    - Errors (5xx): $errors\n";
            echo "    - Error rate: {$error_rate}%\n";
        }
        
        // Check stored file
        $month_dir = storage_path('insights/reliability/sre-metrics') . '/' . $date->format('Y-m');
        $day_file = $month_dir . '/' . $date->format('Y-m-d') . '.json';
        
        if (file_exists($day_file)) {
            $stored = json_decode(file_get_contents($day_file), true);
            echo "\n  📁 Stored file: $day_file\n";
            echo "    - Total API 5xx in storage: " . ($stored['by_request_type']['API']['errors_5xx'] ?? '?') . "\n";
            echo "    - Total UI 5xx in storage: " . ($stored['by_request_type']['UI']['errors_5xx'] ?? '?') . "\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Download/Parse failed!\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "Test completed.\n";
echo "═══════════════════════════════════════════════════════════════\n";
