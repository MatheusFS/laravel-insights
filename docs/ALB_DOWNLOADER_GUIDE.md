# Guia de Uso: ALBLogDownloaderInterface

## 📖 Exemplos de Implementação

### 1. Uso no Controller

```php
namespace App\Http\Controllers\Api;

use Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface;
use MatheusFS\Laravel\Insights\Services\Domain\Metrics\SREMetricsCalculator;
use Illuminate\Http\JsonResponse;

class MetricsController {
    
    public function __construct(
        private ALBLogDownloaderInterface $alb_downloader,
        private SREMetricsCalculator $sre_metrics
    ) {}
    
    /**
     * GET /api/metrics/reliability
     * 
     * Retorna métricas SRE para um período
     */
    public function reliability(): JsonResponse {
        $month = request()->query('month', now()->format('Y-m'));
        
        // Injetar downloader no calculator
        $this->sre_metrics->setALBDownloader($this->alb_downloader);
        
        // Calcular métricas usando logs contínuos
        $metrics = $this->sre_metrics->calculateMonthlyFromContinuousLogs($month);
        
        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
    
    /**
     * GET /api/metrics/logs
     * 
     * Retorna logs ALB brutos agregados por tipo de requisição
     */
    public function logs(): JsonResponse {
        $date = request()->query('date', now()->toDateString());
        
        // Usar downloader diretamente
        $logs = $this->alb_downloader->downloadForDate(
            Carbon::parse($date)
        );
        
        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
```

---

### 2. Uso em Service

```php
namespace App\Services\Reliability;

use Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface;
use Carbon\Carbon;

class SLAComplianceChecker {
    
    public function __construct(
        private ALBLogDownloaderInterface $alb_downloader
    ) {}
    
    /**
     * Verifica se algum serviço violou SLA no período
     */
    public function checkSLABreach(string $month): array {
        // Obter logs agregados do mês
        $logs = $this->alb_downloader->downloadForMonth($month);
        
        $sla_target = 95.0;
        $breaches = [];
        
        foreach (['API', 'UI'] as $service) {
            $total_requests = $logs['by_request_type'][$service]['total_requests'] ?? 0;
            $errors_5xx = $logs['by_request_type'][$service]['errors_5xx'] ?? 0;
            
            if ($total_requests === 0) continue;
            
            $sli = 1 - ($errors_5xx / $total_requests);
            $sli_percent = round($sli * 100, 2);
            
            if ($sli_percent < $sla_target) {
                $breaches[$service] = [
                    'sli' => $sli_percent,
                    'sla' => $sla_target,
                    'breach' => true,
                ];
            }
        }
        
        return $breaches;
    }
}
```

---

### 3. Implementação Customizada (CloudWatch)

```php
namespace App\Services\ALB;

use Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\Carbon;

/**
 * Downloader que busca logs de CloudWatch (AWS)
 * 
 * Implementação customizada que segue o contrato da interface
 */
class CloudWatchALBDownloader implements ALBLogDownloaderInterface {
    
    private CloudWatchLogsClient $client;
    
    public function __construct(private string $storage_path) {
        $this->client = new CloudWatchLogsClient([
            'region' => env('AWS_REGION', 'us-east-1'),
        ]);
    }
    
    public function downloadForDate(Carbon $date, array $options = []): array {
        $log_group = config('insights.alb_log_group', '/aws/elasticloadbalancing/app/refresher');
        
        // Query CloudWatch para pegar logs do ALB
        $response = $this->client->filterLogEvents([
            'logGroupName' => $log_group,
            'startTime' => $date->startOfDay()->getTimestampMs(),
            'endTime' => $date->endOfDay()->getTimestampMs(),
        ]);
        
        // Processar e agregar logs
        return $this->aggregateLogEvents($response['events'] ?? [], $date);
    }
    
    public function downloadForMonth(string $month, array $options = []): array {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        
        $aggregate = [
            'by_request_type' => [
                'API' => ['total_requests' => 0, 'errors_5xx' => 0],
                'UI' => ['total_requests' => 0, 'errors_5xx' => 0],
                'BOT' => ['total_requests' => 0, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 0, 'errors_5xx' => 0],
            ],
        ];
        
        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $day_logs = $this->downloadForDate($date, $options);
            
            // Agregar
            foreach (['API', 'UI', 'BOT', 'ASSETS'] as $service) {
                $aggregate['by_request_type'][$service]['total_requests'] += 
                    $day_logs['by_request_type'][$service]['total_requests'] ?? 0;
                $aggregate['by_request_type'][$service]['errors_5xx'] += 
                    $day_logs['by_request_type'][$service]['errors_5xx'] ?? 0;
            }
        }
        
        return $aggregate;
    }
    
    public function getStoragePath(): string {
        return $this->storage_path;
    }
    
    public function hasDataForDate(Carbon $date): bool {
        // CloudWatch sempre tem dados (não precisa cache)
        return true;
    }
    
    private function aggregateLogEvents(array $events, Carbon $date): array {
        // Processar eventos CloudWatch e agregar por tipo de requisição
        $aggregate = [/* ... */];
        
        foreach ($events as $event) {
            $message = $event['message'];
            // Fazer parsing de ALB log format
            // Contar por tipo de requisição e status code
        }
        
        return $aggregate;
    }
}
```

---

### 4. Registrar Implementação Customizada

```php
// app/Providers/AppServiceProvider.php

use Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface;
use App\Services\ALB\CloudWatchALBDownloader;

public function register() {
    // Bind a implementação CloudWatch ao contrato
    $this->app->singleton(ALBLogDownloaderInterface::class, function ($app) {
        return new CloudWatchALBDownloader(
            storage_path('app/sre_metrics')
        );
    });
}
```

---

### 5. Teste Unitário

```php
namespace Tests\Unit\Services;

use Tests\TestCase;
use Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface;
use Matheusfs\LaravelInsights\Services\Domain\ALBLogAnalyzer;
use Carbon\Carbon;

class ALBLogDownloaderTest extends TestCase {
    
    private ALBLogDownloaderInterface $downloader;
    
    protected function setUp(): void {
        parent::setUp();
        $this->downloader = app(ALBLogDownloaderInterface::class);
    }
    
    public function test_can_download_logs_for_date(): void {
        $date = Carbon::parse('2026-02-05');
        
        $logs = $this->downloader->downloadForDate($date);
        
        $this->assertArrayHasKey('by_request_type', $logs);
        $this->assertArrayHasKey('API', $logs['by_request_type']);
        $this->assertArrayHasKey('UI', $logs['by_request_type']);
    }
    
    public function test_can_aggregate_monthly_logs(): void {
        $month = '2026-02';
        
        $logs = $this->downloader->downloadForMonth($month);
        
        $this->assertIsArray($logs['by_request_type']);
        $this->assertArrayHasKey('period', $logs);
    }
    
    public function test_caches_downloaded_data(): void {
        $date = Carbon::parse('2026-02-05');
        
        // Primeiro download
        $logs1 = $this->downloader->downloadForDate($date);
        
        // Segundo download (deve retornar cache)
        $logs2 = $this->downloader->downloadForDate($date);
        
        $this->assertEquals($logs1, $logs2);
    }
    
    public function test_force_refresh_ignores_cache(): void {
        $date = Carbon::parse('2026-02-05');
        
        // Com cache
        $logs1 = $this->downloader->downloadForDate($date);
        
        // Force refresh
        $logs2 = $this->downloader->downloadForDate($date, ['force' => true]);
        
        // Ambos devem ser válidos (pode ter dados novos)
        $this->assertIsArray($logs1);
        $this->assertIsArray($logs2);
    }
}
```

---

### 6. Feature Test (Integração)

```php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

class SREMetricsEndpointTest extends TestCase {
    
    public function test_can_retrieve_sre_metrics_for_month(): void {
        $response = $this->getJson('/api/insights/reliability/sre-metrics?month=2026-02');
        
        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'services' => [
                        'API' => [],
                        'UI' => [],
                    ],
                    'window' => [],
                    'source' => 'continuous_alb_logs',
                ],
            ]);
    }
    
    public function test_validates_month_format(): void {
        $response = $this->getJson('/api/insights/reliability/sre-metrics?month=02-2026');
        
        $response->assertStatus(422);
    }
    
    public function test_allows_custom_slo_sla(): void {
        $response = $this->getJson(
            '/api/insights/reliability/sre-metrics?month=2026-02&slo_target=99&sla_target=98'
        );
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.services.API.slo.target', 99);
        $response->assertJsonPath('data.services.API.sla.target', 98);
    }
}
```

---

## 🔑 Pontos-Chave

### Interface vs Implementação

| Interface | O Quê | Por Quê |
|-----------|-------|--------|
| `ALBLogDownloaderInterface` | Contrato | Permite múltiplas implementações (local, CloudWatch, S3) |
| `ALBLogDownloader` | Default local | Para desenvolvimento e testes |
| `CloudWatchALBDownloader` | Customizada | Para produção real |

### Injeção de Dependência

```php
// ✅ Correto: Injetar interface
public function __construct(
    private ALBLogDownloaderInterface $downloader
) {}

// ❌ Errado: Injetar implementação específica
public function __construct(
    private ALBLogDownloader $downloader
) {}
```

### Composição vs Herança

```php
// ✅ Preferir: ALBLogDownloader pode ter ALBLogAnalyzer
public function __construct(ALBLogAnalyzer $analyzer) { }

// ❌ Evitar: Herança complexa
class ALBLogDownloader extends AnalyzerBase { }
```

---

## 📞 Suporte

Para dúvidas sobre a interface:
- Veja [SRE_METRICS_CONTINUOUS_LOGS.md](./SRE_METRICS_CONTINUOUS_LOGS.md)
- Explore tests em `tests/Unit/Services/`
- Verifique `ServiceProvider.php` para binding
