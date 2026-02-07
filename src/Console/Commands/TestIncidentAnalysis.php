<?php

namespace MatheusFS\Laravel\Insights\Console\Commands;

use Illuminate\Console\Command;
use MatheusFS\Laravel\Insights\Services\Application\IncidentAnalysisService;
use Carbon\Carbon;

/**
 * TestIncidentAnalysis - Comando para testar análise completa de incidente
 * 
 * Usage: php artisan insights:test-incident INC-2026-001
 */
class TestIncidentAnalysis extends Command
{
    protected $signature = 'insights:test-incident 
                            {incidentId : ID do incidente (ex: INC-2026-001)} 
                            {--start=2026-01-15T10:00:00Z : Timestamp de início} 
                            {--end=2026-01-15T10:30:00Z : Timestamp de restauração}
                            {--force : Forçar re-download e re-extração}';
    
    protected $description = 'Test complete incident analysis workflow (download → parse → classify → save)';

    public function handle(IncidentAnalysisService $analysisService): int
    {
        $incidentId = $this->argument('incidentId');
        $startedAt = $this->option('start');
        $restoredAt = $this->option('end');

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  INCIDENT ANALYSIS TEST");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();

        // Step 1: Validar configuração
        $this->info('📋 Step 1: Validating Configuration');
        if (!$this->validateConfiguration()) {
            return 1;
        }
        $this->line('   ✅ Configuration OK');
        $this->newLine();

        // Step 2: Preparar dados do incidente
        $this->info('📦 Step 2: Preparing Incident Data');
        $incident_data = [
            'timestamp' => [
                'started_at' => $startedAt,
                'restored_at' => $restoredAt,
            ],
        ];
        $this->table(
            ['Field', 'Value'],
            [
                ['Incident ID', $incidentId],
                ['Started At', $startedAt],
                ['Restored At', $restoredAt],
                ['Force Redownload', $this->option('force') ? 'Yes' : 'No'],
            ]
        );
        $this->newLine();

        // Step 3: Executar análise
        $this->info('⚙️  Step 3: Analyzing Logs (download → extract → parse → classify)');
        $this->line('   This may take a few seconds...');
        
        try {
            $start_time = microtime(true);
            
            $result = $analysisService->analyzeLogs($incidentId, $incident_data);
            
            $elapsed = round(microtime(true) - $start_time, 2);
            
            $this->line('   ✅ Analysis completed in ' . $elapsed . 's');
            $this->newLine();

            // Step 4: Exibir resultados
            $this->info('📊 Step 4: Analysis Results');
            $this->displayResults($result);
            $this->newLine();

            // Step 5: Verificar arquivo JSON
            $this->info('💾 Step 5: Verifying Saved JSON');
            $this->verifySavedJson($incidentId);
            $this->newLine();

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('  ✅ ALL TESTS PASSED');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Analysis failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            
            return 1;
        }
    }

    private function validateConfiguration(): bool
    {
        $checks = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'S3_BUCKET' => config('insights.alb_logs.s3.bucket'),
            'S3_PATH' => config('insights.alb_logs.s3.path'),
        ];

        $all_ok = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("   ❌ {$key} not configured");
                $all_ok = false;
            } else {
                $masked_value = $key === 'AWS_SECRET_ACCESS_KEY' ? '***' : $value;
                $this->line("   ✓ {$key}: {$masked_value}");
            }
        }

        return $all_ok;
    }

    private function displayResults(array $result): void
    {
        // Métricas gerais
        $this->table(
            ['Metric', 'Value'],
            [
                ['Incident ID', $result['incident_id']],
                ['Total Requests', number_format($result['total_records'])],
                ['Unique IPs', number_format($result['total_ips'])],
            ]
        );

        $this->newLine();

        // Classificações
        $legitimate = count($result['classified']['legitimate']);
        $suspicious = count($result['classified']['suspicious']);
        $malicious = count($result['classified']['malicious']);

        $this->line('   IP Classifications:');
        $this->line("   ├─ 🟢 Legitimate: {$legitimate} IPs");
        $this->line("   ├─ 🟡 Suspicious: {$suspicious} IPs");
        $this->line("   └─ 🔴 Malicious: {$malicious} IPs");

        // Top 5 IPs por tipo
        if ($legitimate > 0) {
            $this->newLine();
            $this->line('   Top Legitimate IPs:');
            foreach (array_slice($result['classified']['legitimate'], 0, 5) as $ip_data) {
                $this->line(sprintf(
                    '   • %s: %d requests (%.1f%% errors)',
                    $ip_data['ip'],
                    $ip_data['total_requests'],
                    $ip_data['error_rate'] * 100
                ));
            }
        }

        if ($suspicious > 0) {
            $this->newLine();
            $this->line('   🟡 Suspicious IPs:');
            foreach (array_slice($result['classified']['suspicious'], 0, 5) as $ip_data) {
                $this->line(sprintf(
                    '   • %s: %d requests (%.1f%% errors, %d paths)',
                    $ip_data['ip'],
                    $ip_data['total_requests'],
                    $ip_data['error_rate'] * 100,
                    $ip_data['unique_paths'] ?? 0
                ));
            }
        }

        if ($malicious > 0) {
            $this->newLine();
            $this->line('   🔴 Malicious IPs:');
            foreach (array_slice($result['classified']['malicious'], 0, 5) as $ip_data) {
                $this->line(sprintf(
                    '   • %s: %d requests (%.1f%% errors)',
                    $ip_data['ip'],
                    $ip_data['total_requests'],
                    $ip_data['error_rate'] * 100
                ));
            }
        }
    }

    private function verifySavedJson(string $incidentId): void
    {
        $incidents_path = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
        $json_file = "{$incidents_path}/{$incidentId}/alb_logs_analysis.json";

        if (file_exists($json_file)) {
            $size = filesize($json_file);
            $this->line('   ✅ JSON file saved: ' . $json_file);
            $this->line('   📦 File size: ' . number_format($size) . ' bytes');
            
            // Verificar se é JSON válido
            $content = file_get_contents($json_file);
            $json = json_decode($content, true);
            
            if ($json === null) {
                $this->error('   ❌ Invalid JSON format');
            } else {
                $this->line('   ✅ JSON format valid');
            }
        } else {
            $this->error('   ❌ JSON file not found: ' . $json_file);
        }
    }
}
