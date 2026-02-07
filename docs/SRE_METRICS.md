# SRE Metrics - Fluxo de Dados

> Guia de uso do sistema de métricas SRE (SLI, SLO, SLA, Error Budget)
> Atualizado em: 2026-02-07

---

## 🎯 Visão Geral

As **SRE Metrics** calculam indicadores de confiabilidade do serviço baseados em logs ALB:

- **SLI (Service Level Indicator)**: Métrica observada real (fato)
- **SLO (Service Level Objective)**: Meta operacional interna (ex: 98.5%)
- **SLA (Service Level Agreement)**: Compromisso contratual (ex: 95%)
- **Error Budget**: Margem de erro baseada no SLA

---

## 📊 Diferença: Logs de Incidentes vs Logs Contínuos

| Tipo | Propósito | Fonte | Storage | Usado por |
|------|-----------|-------|---------|-----------|
| **Logs de Incidentes** | Análise post-mortem de incidente específico | S3 (período do incidente) | `storage/insights/reliability/incidents/{id}/` | Análise de incidentes, WAF, usuários afetados |
| **Logs Contínuos (SRE)** | Métricas mensais agregadas para SLO/SLA | S3 (dia inteiro) | `storage/app/sre_metrics/{Y-m}/` | Endpoint `/api/insights/reliability/sre-metrics` |

**IMPORTANTE:** Logs de incidentes **NÃO** alimentam métricas SRE. São sistemas paralelos.

---

## 🚀 Como Popular Métricas SRE

### 1. Automático (Recomendado) ✨

Ao acessar o endpoint `/api/insights/reliability/sre-metrics`, o sistema **automaticamente**:

1. Detecta ausência de dados
2. Dispara download de logs em background (Job)
3. Retorna status `202 Accepted` com mensagem de processamento
4. Frontend aguarda 2-5 minutos e tenta novamente

**Experiência do usuário:**
- Primeira tentativa: "Processando logs... aguarde 5 minutos"
- Sistema baixa logs do S3 em background
- Após 5 minutos: Página atualiza automaticamente com dados reais

### 2. Baixar Logs Manualmente (Opcional)

Se quiser popular antes de acessar a UI:

```bash
# Baixar logs do mês atual
docker exec core-fpm-1 php artisan alb:download-logs --month=2026-02

# Baixar logs de um dia específico
docker exec core-fpm-1 php artisan alb:download-logs --date=2026-02-05

# Forçar re-download (ignorar cache)
docker exec core-fpm-1 php artisan alb:download-logs --month=2026-02 --force
```

### 3. Agendar Download Diário (Produção)

No `app/Console/Kernel.php` da aplicação consumidora:

```php
protected function schedule(Schedule $schedule)
{
    // Baixa logs do dia anterior todo dia às 00:30
    $schedule->command('alb:download-logs')
             ->dailyAt('00:30')
             ->withoutOverlapping();
}
```

### 3. Verificar se Logs Existem

```bash
ls -la storage/app/sre_metrics/2026-02/

# Deve mostrar:
# 2026-02-01.json
# 2026-02-02.json
# ...
# monthly_aggregate.json
```

---

## 📁 Estrutura de Armazenamento

```
storage/app/sre_metrics/
├── 2026-02/
│   ├── 2026-02-01.json          # Logs do dia 1
│   ├── 2026-02-02.json          # Logs do dia 2
│   ├── ...
│   ├── 2026-02-07.json          # Logs do dia 7
│   └── monthly_aggregate.json   # Agregado do mês inteiro
└── 2026-03/
    └── ...
```

### Estrutura de `{date}.json`:

```json
{
  "by_request_type": {
    "API": {
      "total_requests": 15234,
      "errors_5xx": 45,
      "errors_4xx": 230,
      "error_rate": 1.8,
      "unique_ips_with_errors": 12
    },
    "UI": {
      "total_requests": 42301,
      "errors_5xx": 18,
      "errors_4xx": 89,
      "error_rate": 0.25,
      "unique_ips_with_errors": 8
    },
    "BOT": { ... },
    "ASSETS": { ... }
  },
  "period": {
    "start": "2026-02-01T00:00:00-03:00",
    "end": "2026-02-01T23:59:59-03:00"
  },
  "timestamp": "2026-02-02T00:30:15-03:00"
}
```

---

## 🔧 Endpoint API

### GET `/api/insights/reliability/sre-metrics`

**Query params:**
- `month` (opcional): Mês no formato `Y-m` (ex: `2026-02`). Padrão: mês atual
- `slo_target` (opcional): Meta SLO em % (ex: `98.5`). Padrão: config
- `sla_target` (opcional): Meta SLA em % (ex: `95.0`). Padrão: config

**Resposta com dados:**
```json
{
  "success": true,
  "data": {
    "services": {
      "API": {
        "raw": { "total_requests": 450123, "total_5xx": 523 },
        "sli": { "value": 99.8838, "unit": "%", "description": "..." },
        "slo": { "target": 98.5, "unit": "%", "breached": false, "description": "..." },
        "sla": { "target": 95.0, "unit": "%", "at_risk": false, "description": "..." },
        "error_budget": {
          "total": 5.0,
          "used": 0.1162,
          "remaining": 4.8838,
          "unit": "%",
          "depleted": false,
          "description": "..."
        },
        "status": {
          "operational": true,
          "slo_violation": false,
          "sla_risk": false,
          "healthy": true
        }
      },
      "UI": { ... }
    },
    "window": {
      "start": "2026-02-01T00:00:00-03:00",
      "end": "2026-02-28T23:59:59-03:00",
      "type": "monthly_cumulative"
    },
    "calculated_at": "2026-02-07T14:30:00-03:00",
    "source": "continuous_alb_logs"
  }
}
```

**Resposta sem dados (logs sendo processados):**
```json
{
  "success": false,
  "error": "processing",
  "message": "Logs ALB estão sendo baixados em background. Aguarde 2-5 minutos e tente novamente.",
  "estimated_time_minutes": 5,
  "data": {
    "services": {
      "API": { "raw": { "total_requests": 0, "total_5xx": 0 }, ... },
      "UI": { "raw": { "total_requests": 0, "total_5xx": 0 }, ... }
    },
    ...
  }
}
```

**Status HTTP:** `202 Accepted` (processamento em andamento)

**Comportamento:** Sistema dispara `DownloadSRELogsJob` automaticamente em background.

---

## ⚙️ Configuração

### Queue Configuration

O sistema usa Laravel Queue para download em background. Configure no `.env`:

```bash
# Queue driver (database, redis, sync para dev)
QUEUE_CONNECTION=redis

# Se usar redis
REDIS_HOST=redis
REDIS_PORT=6379
```

**Workers em produção:**
```bash
# Supervisord ou systemd
php artisan queue:work --queue=default --tries=2 --timeout=600
```

**Desenvolvimento (sync):**
```bash
# Para testar sem worker
QUEUE_CONNECTION=sync
```

### Package Configuration

No `config/insights.php`:

```php
return [
    // Caminho de armazenamento de logs SRE
    'sre_metrics_path' => env('SRE_METRICS_PATH', storage_path('app/sre_metrics')),
    
    // Targets padrão de SRE
    'sre_targets' => [
        'API' => [
            'slo' => 98.5,  // Meta operacional interna
            'sla' => 95.0,  // Compromisso contratual
        ],
        'UI' => [
            'slo' => 98.5,
            'sla' => 95.0,
        ],
    ],
];
```

---

## 🔍 Troubleshooting

### Problema: Métricas zeradas mesmo com incidente registrado

**Causa:** Logs de incidentes são diferentes de logs contínuos. Incidentes usam período específico; SRE Metrics usam dia inteiro.

**Solução:** Aguarde alguns minutos. Na primeira tentativa, o sistema automaticamente dispara o download em background.

### Problema: Processamento demora mais de 5 minutos

**Causa:** Volume grande de logs no S3 ou S3 lento.

**Solução:** Aguarde mais alguns minutos. O job pode levar até 10 minutos em meses com tráfego alto. Verifique logs:
```bash
docker exec core-fpm-1 tail -f storage/logs/laravel.log | grep DownloadSRELogsJob
```

### Problema: `RuntimeException: ALB Downloader not injected`

**Causa:** Controller não conseguiu resolver `ALBLogDownloaderInterface`.

**Solução:** Verificar binding no `ServiceProvider`:
```php
$this->app->singleton(ALBLogDownloaderInterface::class, function ($app) {
    return new S3ALBLogDownloader(...);
});
```

### Problema: Logs não aparecem no storage

**Causa:** AWS credentials inválidas ou bucket não acessível.

**Solução:** Verificar `.env`:
```bash
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=refresher-logs
```

---

## 📚 Referências

- [SREMetricsCalculator.php](../src/Services/Domain/Metrics/SREMetricsCalculator.php) - Lógica de cálculo
- [S3ALBLogDownloader.php](../src/Services/Domain/S3ALBLogDownloader.php) - Download e agregação
- [DownloadALBLogsCommand.php](../src/Console/Commands/DownloadALBLogsCommand.php) - Comando CLI
- [DownloadSRELogsJob.php](../src/Jobs/DownloadSRELogsJob.php) - Job em background (auto-trigger)
- [IncidentAnalysisApiController.php](../src/Http/Controllers/IncidentAnalysisApiController.php) - Endpoint API

---

**Versão:** 2.0 (Download Automático)  
**Tipo:** Documentação de Fluxo  
**Última Atualização:** 2026-02-07
