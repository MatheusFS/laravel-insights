# S3 ALB Log Downloader - Documentação

> Como conectar ao S3 real para download contínuo de logs ALB do Refresher
> Atualizado em: 2026-02-06

---

## 🎯 Visão Geral

O **S3ALBLogDownloader** é a implementação real que conecta ao AWS S3, baixa logs do Application Load Balancer (ALB), analisa e armazena em formato JSON diário para cálculo de métricas SRE.

### Diferença entre Implementações

| Implementação | Fonte | Uso |
|---------------|-------|-----|
| **ALBLogDownloader** (local/mock) | Arquivos locais ou dados vazios | Desenvolvimento/testes sem AWS |
| **S3ALBLogDownloader** (produção) | AWS S3 bucket real | Produção com dados reais do ALB |

---

## 🏗️ Arquitetura

```
S3ALBLogDownloader (Domain)
    ↓ usa
S3LogDownloaderService (Infrastructure)
    ↓ busca de
AWS S3 Bucket (refresher-logs)
    ↓ retorna logs .gz
ALBLogAnalyzer (Domain)
    ↓ classifica em
API / UI / BOT / ASSETS
    ↓ salva em
storage/app/sre_metrics/YYYY-MM/YYYY-MM-DD.json
```

**Fluxo:**
1. `S3ALBLogDownloader.downloadForDate()` recebe data
2. Chama `S3LogDownloaderService` para baixar logs do S3
3. Parseia arquivos .log do ALB (formato específico AWS)
4. Passa para `ALBLogAnalyzer` classificar por tipo
5. Salva JSON diário com agregação

---

## ⚙️ Configuração

### 1. Arquivo `.env`

```bash
# Fonte de logs (local, s3, cloudwatch)
ALB_LOG_SOURCE=s3

# AWS S3 Configuration para logs ALB
AWS_ALB_LOGS_BUCKET=refresher-logs
AWS_ALB_LOGS_PATH=AWSLogs/624082998591/elasticloadbalancing/us-east-1
AWS_REGION=us-east-1

# Caminho de armazenamento de métricas SRE
SRE_METRICS_PATH=/var/www/html/storage/app/sre_metrics

# AWS Credentials (já existem no .env, não armazenar aqui)
AWS_ACCESS_KEY_ID=YOUR_AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION=us-east-1
```

### 2. Service Provider (Automático)

O `ServiceProvider` seleciona automaticamente a implementação baseado em `ALB_LOG_SOURCE`:

```php
// Em MatheusFS\Laravel\Insights\ServiceProvider
$this->app->singleton(ALBLogDownloaderInterface::class, function ($app) {
    $source = config('insights.alb_source', 'local');
    
    if ($source === 's3') {
        // Usa implementação S3
        return new S3ALBLogDownloader(
            $app->make(ALBLogAnalyzer::class),
            $app->make(S3LogDownloaderService::class),
            config('insights.sre_metrics_path')
        );
    }
    
    // Default: Local/Mock
    return new ALBLogDownloader(...);
});
```

### 3. S3 Bucket Structure

Os logs do ALB no S3 seguem estrutura padrão da AWS:

```
s3://refresher-logs/
└── AWSLogs/
    └── 624082998591/                    # Account ID
        └── elasticloadbalancing/
            └── us-east-1/              # Region
                └── 2026/
                    └── 02/             # Mês
                        └── 06/         # Dia
                            ├── 624082998591_elasticloadbalancing_us-east-1_app.refresher-alb.xxx_20260206T0000Z_xxx.log.gz
                            ├── 624082998591_elasticloadbalancing_us-east-1_app.refresher-alb.xxx_20260206T0100Z_xxx.log.gz
                            └── ... (um arquivo por intervalo de 5-60 minutos)
```

---

## 🚀 Comandos

### Download de Logs

```bash
# Baixar logs de uma data específica
php artisan alb:download-logs --date=2026-02-06

# Baixar logs de um mês completo
php artisan alb:download-logs --month=2026-02

# Forçar re-download (ignora cache)
php artisan alb:download-logs --date=2026-02-06 --force
```

### Verificação

```bash
# Ver arquivos JSON gerados
ls -lh storage/app/sre_metrics/2026-02/

# Ver logs raw baixados do S3 (temporários)
ls -lh storage/app/sre_metrics/.raw_logs/SRE-2026-02-06/

# Ver conteúdo de um dia
cat storage/app/sre_metrics/2026-02/2026-02-06.json | jq
```

### Agendamento Automático

```php
// Em core/app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('alb:download-logs')
        ->dailyAt('00:30')
        ->withoutOverlapping()
        ->runInBackground();
}
```

---

## 📊 Formato de Dados

### Logs Raw do ALB (AWS)

Formato do arquivo `.log` após descompactar `.gz`:

```
http 2026-02-06T12:34:56.789012Z app/refresher-alb/50dc6c495c0c9188 192.168.1.1:41898 10.0.1.23:80 0.000 0.001 0.000 200 200 722 29086 "GET https://refresher.com.br:443/api/briefings HTTP/1.1" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:aws:elasticloadbalancing:us-east-1:624082998591:targetgroup/refresher/50dc6c495c0c9188 "Root=1-5e" "refresher.com.br" "arn:aws:acm:us-east-1:624082998591:certificate/xxx" 0 2026-02-06T12:34:56.789012Z "forward" "-" "-" "10.0.1.23:80" "200" "-" "-"
```

### JSON Diário Gerado

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
    },
    "BOT": {
      "total_requests": 8234,
      "errors_5xx": 12
    },
    "ASSETS": {
      "total_requests": 125678,
      "errors_5xx": 45
    }
  },
  "period": {
    "start": "2026-02-06T00:00:00+00:00",
    "end": "2026-02-06T23:59:59+00:00"
  },
  "timestamp": "2026-02-07T00:35:12+00:00"
}
```

---

## 🔍 Parsing de Logs ALB

O método `parseALBLogLine()` extrai campos críticos:

```php
[
    'timestamp' => '2026-02-06T12:34:56.789012Z',
    'status_code' => 200,              // elb_status_code
    'method' => 'GET',
    'path' => '/api/briefings',
    'user_agent' => 'Mozilla/5.0...',
    'received_bytes' => 722,
    'sent_bytes' => 29086,
]
```

### Classificação por Tipo

O `ALBLogAnalyzer` usa padrões para classificar:

```php
'API' => [
    'path' => ['^/api/', '^/v\d+/'],
    'user_agent' => ['axios', 'fetch', 'curl'],
],
'UI' => [
    'path' => ['^/$', '^/briefing', '^/project', '^/dashboard'],
    'user_agent' => ['Mozilla', 'Chrome', 'Safari', 'Firefox'],
],
'BOT' => [
    'user_agent' => ['bot', 'crawler', 'spider', 'googlebot'],
],
'ASSETS' => [
    'path' => ['\.(js|css|png|jpg|gif|ico|svg|woff)$'],
],
```

---

## 🐛 Troubleshooting

### 1. "Total de requisições: 0" mesmo com S3 configurado

**Causas:**
- S3 bucket não tem logs para essa data
- Permissões IAM insuficientes (precisa `s3:GetObject`, `s3:ListBucket`)
- Path incorreto no config (`AWS_ALB_LOGS_PATH`)

**Verificar:**
```bash
# Testar acesso ao S3 via AWS CLI
aws s3 ls s3://refresher-logs/AWSLogs/624082998591/elasticloadbalancing/us-east-1/2026/02/06/ --region us-east-1

# Ver logs do Laravel
tail -f storage/logs/laravel.log
```

### 2. Parsing de logs retorna vazio

**Causas:**
- Formato do log ALB mudou (AWS atualiza formato)
- Regex em `parseALBLogLine()` não corresponde

**Solução:**
```bash
# Ver log raw baixado
cat storage/app/sre_metrics/.raw_logs/SRE-2026-02-06/*.log | head -5

# Ajustar regex no método parseALBLogLine()
```

### 3. Memory limit ao processar mês completo

**Causas:**
- Muitos logs (milhões de linhas)
- PHP memory_limit baixo

**Solução:**
```bash
# Aumentar memory_limit temporariamente
php -d memory_limit=2G artisan alb:download-logs --month=2026-02

# Ou processar dia por dia
for day in {01..28}; do
    php artisan alb:download-logs --date=2026-02-$day
done
```

---

## 📈 Métricas Geradas

Com dados reais do S3, as métricas SRE são calculadas:

```bash
# Calcular SLI, SLO, SLA para o mês
curl "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02" | jq
```

**Resposta:**
```json
{
  "data": {
    "services": {
      "API": {
        "total_requests": 1980150,
        "errors_5xx": 42345,
        "sli": 97.86,          // 1 - (42345 / 1980150) * 100
        "slo_target": 98.5,
        "sla_target": 95.0,
        "meets_slo": false,     // 97.86 < 98.5
        "meets_sla": true,      // 97.86 >= 95.0
        "error_budget": 5.0,    // 1 - (95.0 / 100)
        "error_budget_consumed": 57.2  // ((100 - 97.86) / 5.0) * 100
      },
      "UI": { ... }
    }
  }
}
```

---

## 🔐 Permissões IAM Necessárias

Para a conta AWS `624082998591` (Refresher):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::refresher-logs/*",
        "arn:aws:s3:::refresher-logs"
      ]
    }
  ]
}
```

---

## 🔄 Workflow Completo

```
1. [00:30 daily] Cron dispara: php artisan alb:download-logs
                      ↓
2. S3ALBLogDownloader.downloadForDate(yesterday)
                      ↓
3. S3LogDownloaderService busca logs .gz do S3
   - Baixa para storage/app/sre_metrics/.raw_logs/SRE-YYYY-MM-DD/
   - Descompacta .gz → .log
                      ↓
4. parseALBLogFile() lê cada .log
   - Parseia linhas com regex
   - Extrai: timestamp, status_code, method, path, user_agent
                      ↓
5. ALBLogAnalyzer.analyze()
   - Classifica por tipo (API/UI/BOT/ASSETS)
   - Conta total_requests e errors_5xx
                      ↓
6. Salva em storage/app/sre_metrics/YYYY-MM/YYYY-MM-DD.json
                      ↓
7. [On demand] GET /api/insights/reliability/sre-metrics?month=YYYY-MM
   - Lê monthly_aggregate.json
   - Calcula SLI, SLO, SLA, Error Budget
   - Retorna JSON para frontend
```

---

## 📚 Arquivos Relacionados

| Arquivo | Responsabilidade |
|---------|------------------|
| `S3ALBLogDownloader.php` | Implementação S3 do downloader |
| `S3LogDownloaderService.php` | Infraestrutura de acesso ao S3 |
| `ALBLogAnalyzer.php` | Classificação de logs por tipo |
| `ALBLogDownloaderInterface.php` | Interface do contrato |
| `ServiceProvider.php` | Binding condicional (local vs s3) |
| `DownloadALBLogsCommand.php` | Artisan command |
| `IncidentAnalysisApiController.php` | Endpoint da API |
| `config/insights.php` | Configurações |

---

## 🎓 Conceitos Importantes

### Por que Baixar Logs Diariamente?

**Antes:** Sistema só baixava logs durante incidentes (1-3 horas)
**Problema:** Não era possível calcular SLI de um mês completo
**Solução:** Download diário contínuo = cálculo preciso de SLI/SLO/SLA

### Por que S3 e não CloudWatch?

**CloudWatch Logs:**
- ✅ Dados estruturados (já parseados)
- ❌ Custo alto para queries longas
- ❌ Retenção limitada (padrão: 30 dias)

**S3 Logs:**
- ✅ Custo baixíssimo (armazenamento)
- ✅ Retenção ilimitada
- ✅ Formato padrão ALB
- ❌ Precisa parsear manualmente

**Decisão:** S3 é ideal para histórico longo (anos) com custo controlado.

---

## ✅ Checklist de Produção

- [ ] `ALB_LOG_SOURCE=s3` em `.env` de produção
- [ ] AWS credentials configuradas com permissões corretas
- [ ] S3 bucket `refresher-logs` acessível
- [ ] Path correto: `AWS_ALB_LOGS_PATH=AWSLogs/624082998591/elasticloadbalancing/us-east-1`
- [ ] Cron agendado para 00:30 daily
- [ ] Storage path com permissões de escrita
- [ ] Teste manual: `php artisan alb:download-logs --date=<hoje>` retorna dados
- [ ] API endpoint retorna métricas reais: `GET /api/insights/reliability/sre-metrics?month=<mes-atual>`
- [ ] Monitorar logs: `tail -f storage/logs/laravel.log` durante primeiro download

---

**Versão:** 1.0  
**Tipo:** Guia de Implementação  
**Status:** ✅ Implementado  
**Atualizado:** 2026-02-06
