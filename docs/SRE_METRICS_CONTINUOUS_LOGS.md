# SRE Metrics: Logs Contínuos do ALB

## 📋 Visão Geral

Sistema de download e análise **contínua** de logs ALB (Application Load Balancer) para cálculo acurado de métricas SRE.

**Problema anterior:** O sistema lia logs apenas durante períodos de incidentes (janela limitada).  
**Solução atual:** Download automático e diário de logs ALB para acumular dados de todo o mês.

---

## 🏗️ Arquitetura

```
Package (laravel-insights):
├── Contracts/ALBLogDownloaderInterface.php       (Abstração)
├── Services/Domain/ALBLogDownloader.php           (Implementação)
├── Services/Domain/ALBLogAnalyzer.php             (Análise de logs)
├── Console/Commands/DownloadALBLogsCommand.php   (Comando Artisan)
└── ServiceProvider.php                            (Registro no DI)

Application (core):
└── app/Console/Kernel.php                        (Agendamento específico)
```

---

## 📦 Componentes

### 1. ALBLogDownloaderInterface

Define contrato para download de logs:

```php
interface ALBLogDownloaderInterface {
    public function downloadForDate(Carbon $date, array $options = []): array;
    public function downloadForMonth(string $month, array $options = []): array;
    public function getStoragePath(): string;
    public function hasDataForDate(Carbon $date): bool;
}
```

**Permite:** Diferentes implementações (CloudWatch, S3, local, mock)

### 2. ALBLogDownloader

Implementação concreta:

```php
class ALBLogDownloader implements ALBLogDownloaderInterface {
    // Baixa logs para uma data
    public function downloadForDate(Carbon $date, array $options = []): array
    
    // Agrega logs para um mês inteiro
    public function downloadForMonth(string $month, array $options = []): array
}
```

**Armazenamento:**
```
storage/app/sre_metrics/
  └── 2026-02/
      ├── 2026-02-01.json          (Logs do dia 01)
      ├── 2026-02-02.json          (Logs do dia 02)
      ├── ...
      └── monthly_aggregate.json    (Agregado do mês)
```

### 3. ALBLogAnalyzer

Classifica requisições por tipo (API, UI, BOT, ASSETS):

```php
class ALBLogAnalyzer {
    public function analyze(array $logs, Carbon $date): array {
        // Retorna agregação por tipo de serviço
        return [
            'by_request_type' => [
                'API' => ['total_requests' => 66005, 'errors_5xx' => 1512],
                'UI' => ['total_requests' => 40434, 'errors_5xx' => 1809],
                'BOT' => ['total_requests' => 13106, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 1273, 'errors_5xx' => 0],
            ],
        ];
    }
}
```

### 4. DownloadALBLogsCommand

Comando Artisan para download de logs:

```bash
# Download de ontem (para agendamento diário)
php artisan alb:download-logs

# Download de data específica
php artisan alb:download-logs --date=2026-02-05

# Download de mês inteiro
php artisan alb:download-logs --month=2026-02

# Ignorar cache e forçar novo download
php artisan alb:download-logs --force
```

### 5. SREMetricsCalculator (melhorado)

Novo método para usar logs contínuos:

```php
$calculator = app(SREMetricsCalculator::class);
$calculator->setALBDownloader($alb_downloader);

// Calcula SLI, SLO, SLA, Error Budget para o mês
$metrics = $calculator->calculateMonthlyFromContinuousLogs('2026-02');

// Resultado:
[
    'services' => [
        'API' => [
            'sli' => ['value' => 97.71, 'unit' => '%'],
            'slo' => ['value' => 98.5, 'target_breach' => false],
            'sla' => ['value' => 95.0, 'at_risk' => false],
            'error_budget' => [...]
        ],
        'UI' => [...],
    ],
    'window' => [
        'start' => '2026-02-01T00:00:00Z',
        'end' => '2026-02-28T23:59:59Z',
        'type' => 'monthly_cumulative',
    ],
    'source' => 'continuous_alb_logs',
]
```

---

## 🔌 Integração no Core

No seu `Kernel.php`, agende o comando:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Rodar todo dia às 00:30 para baixar logs do dia anterior
    $schedule->command('alb:download-logs')
             ->dailyAt('00:30')
             ->withoutOverlapping()
             ->runInBackground();
    
    // Opcional: Popular dados históricos no começo do mês
    if (now()->day === 1) {
        $schedule->command('alb:download-logs --month=' . now()->format('Y-m'))
                 ->at('01:00')
                 ->onSuccess(fn() => Log::info('Historical logs populated'))
                 ->onFailure(fn() => Log::error('Failed to populate historical logs'));
    }
}
```

---

## 🚀 API Endpoint

### GET /api/insights/reliability/sre-metrics

Retorna métricas SRE calculadas a partir de logs contínuos.

**Query Params:**
```
?month=2026-02              (Padrão: mês atual)
&slo_target=98.5            (Padrão: 98.5%)
&sla_target=95.0            (Padrão: 95%)
```

**Response:**
```json
{
  "success": true,
  "data": {
    "services": {
      "API": {
        "raw": {
          "total_requests": 66005,
          "total_5xx": 1512
        },
        "sli": {
          "value": 97.71,
          "unit": "%",
          "description": "Service Level Indicator - métrica observada"
        },
        "slo": {
          "value": 98.5,
          "target": 98.5,
          "status": "BREACHED",
          "description": "Meta operacional interna"
        },
        "sla": {
          "value": 95.0,
          "target": 95.0,
          "status": "OK",
          "description": "Compromisso contratual"
        },
        "error_budget": {
          "total_percent": 5.0,
          "used_percent": 2.29,
          "remaining_percent": 2.71,
          "status": "AVAILABLE"
        }
      },
      "UI": { ... }
    },
    "window": {
      "start": "2026-02-01T00:00:00Z",
      "end": "2026-02-28T23:59:59Z",
      "type": "monthly_cumulative"
    },
    "calculated_at": "2026-02-06T12:30:00Z",
    "source": "continuous_alb_logs"
  }
}
```

---

## 📝 Configuração

No `.env` da aplicação:

```bash
# Storage path para logs contínuos
SRE_METRICS_PATH=storage/app/sre_metrics

# Fonte de logs ALB
ALB_LOG_SOURCE=local          # 'local' para dev, 'cloudwatch' para produção
```

No `config/insights.php`:

```php
'sre_metrics_path' => env('SRE_METRICS_PATH', storage_path('app/sre_metrics')),
'alb_source' => env('ALB_LOG_SOURCE', 'local'),
```

---

## 🧪 Testes

### Teste Manual

```bash
# Simular download para ontem
docker exec core-fpm-1 php artisan alb:download-logs

# Testar cálculo de SRE metrics
curl "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02"
```

### Desenvolvimento: Usando Mock Data

Crie arquivo mock:

```
storage/app/alb_logs_mock/2026-02-06.json
```

Conteúdo:

```json
{
  "by_request_type": {
    "API": {
      "total_requests": 66005,
      "errors_5xx": 1512
    },
    "UI": {
      "total_requests": 40434,
      "errors_5xx": 1809
    }
  }
}
```

---

## 🔄 Migration: De Logs de Incidentes para Contínuos

### Fase 1: Compatibilidade (Atual)

- ✅ Novo método `calculateMonthlyFromContinuousLogs()`
- ✅ Novo endpoint `/api/insights/reliability/sre-metrics`
- ✅ Logs contínuos em `storage/app/sre_metrics/`
- ✅ Método antigo ainda funciona (logs de incidentes)

### Fase 2: Deprecação (Próxima)

- ⚠️ Marcar `calculateMonthlyFromLogs()` como deprecated
- ⚠️ Avisar aplicações para usar novo endpoint
- ⚠️ Documentar migração

### Fase 3: Remoção (Futura)

- ❌ Remover método antigo
- ❌ Remover suporte a logs de incidentes
- ❌ Logs contínuos = única fonte de verdade

---

## 📚 Referências

- [ALBLogDownloaderInterface](../src/Contracts/ALBLogDownloaderInterface.php)
- [ALBLogDownloader](../src/Services/Domain/ALBLogDownloader.php)
- [ALBLogAnalyzer](../src/Services/Domain/ALBLogAnalyzer.php)
- [SREMetricsCalculator](../src/Services/Domain/Metrics/SREMetricsCalculator.php)
- [DownloadALBLogsCommand](../src/Console/Commands/DownloadALBLogsCommand.php)

---

## 🎯 Próximos Passos

1. **Implementação CloudWatch** (Produção)
   - Buscar logs do AWS CloudWatch real
   - Filtrar por status code, tipo de requisição, etc.

2. **Dashboard de Histórico**
   - Gráficos SLI ao longo do tempo
   - Comparação de períodos
   - Tendências de confiabilidade

3. **Alertas Automáticos**
   - Disparar alerta quando SLI < SLO
   - Notificar quando Error Budget < 1%
   - Integração com PagerDuty/Slack

---

**Versão:** 1.0  
**Status:** Production Ready  
**Atualizado:** 2026-02-06
