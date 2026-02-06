# Configuration Structure - Laravel Insights

> Guia de estrutura de configuração separando dados sensíveis (`.env`) de dados não-sensíveis (`config/insights.php`)

---

## 📋 Princípios

1. **Dados Sensíveis** → `.env` (não versionado)
   - AWS Credentials, bucket names com IDs de conta
   - Chaves de API, tokens
   - Paths específicos de infraestrutura

2. **Dados Não-Sensíveis** → `config/insights.php` (versionado)
   - Storage paths de aplicação
   - Configurações de negócio (SLO/SLA)
   - Thresholds e limites
   - Nomes de serviços
   - Padrões de exclusão

---

## 🔐 Separação Atual

### 1. Incident Correlation

**Sensível (`.env`)**
```bash
# AWS S3 credentials (sensível - conta interna)
AWS_INCIDENT_S3_BUCKET=refresher-logs
AWS_INCIDENT_S3_PATH=AWSLogs/[ACCOUNT_ID]/elasticloadbalancing/us-east-1
```

**Não-sensível (`config/insights.php`)**
```php
'incident_correlation' => [
    'enabled' => true,
    'storage_path' => env('INSIGHTS_STORAGE_PATH', base_path('docs/...')),
    
    's3_bucket' => env('AWS_INCIDENT_S3_BUCKET', 'refresher-logs'),
    's3_path' => env('AWS_INCIDENT_S3_PATH', 'AWSLogs/...'),
    'aws_region' => 'us-east-1', // Público
    
    'ip_classification' => [
        'malicious' => ['error_rate_min' => 0.95, 'volume_min' => 200],
        'suspicious' => ['error_rate_min' => 0.90, 'path_scanning_threshold' => 100],
    ],
    
    'default_lookback_hours' => 24,
],
```

---

### 2. ALB Logs (SRE Metrics)

**Sensível (`.env`)**
```bash
# AWS S3 configuration
ALB_LOG_SOURCE=s3
AWS_ALB_LOGS_BUCKET=refresher-logs
AWS_ALB_LOGS_PATH=AWSLogs/[ACCOUNT_ID]/elasticloadbalancing/us-east-1

# (Opcional) CloudWatch
AWS_CLOUDWATCH_LOG_GROUP=/aws/elasticloadbalancing/refresher
```

**Não-sensível (`config/insights.php`)**
```php
'alb_logs' => [
    'source' => env('ALB_LOG_SOURCE', 's3'), // Qual fonte usar
    
    's3' => [
        'bucket' => env('AWS_ALB_LOGS_BUCKET', 'refresher-logs'),
        'path' => env('AWS_ALB_LOGS_PATH', 'AWSLogs/...'),
        'region' => 'us-east-1', // Público
    ],
    
    'cloudwatch' => [
        'log_group' => env('AWS_CLOUDWATCH_LOG_GROUP', '/aws/elasticloadbalancing/...'),
        'region' => 'us-east-1', // Público
    ],
],

'sre_metrics_storage' => storage_path('app/sre_metrics'),
```

---

### 3. SRE Targets (SLO/SLA)

**Não-sensível (apenas `config/insights.php`)**
```php
'sre_targets' => [
    'API' => [
        'slo' => 98.5,  // SLO (meta operacional)
        'sla' => 95.0,  // SLA (compromisso contratual)
    ],
    'UI' => [
        'slo' => 98.0,
        'sla' => 95.0,
    ],
    'BOT' => [
        'slo' => 95.0,
        'sla' => 90.0,
    ],
    'ASSETS' => [
        'slo' => 99.5,
        'sla' => 98.0,
    ],
],
```

---

### 4. ALB Analysis

**Não-sensível (apenas `config/insights.php`)**
```php
'alb_analysis' => [
    'enabled' => true,
    'batch_size' => 10000,        // Linhas por lote
    'timeout_seconds' => 300,     // Timeout máximo
    'exclude_bots' => [           // Patterns de exclusão
        'bot',
        'crawler',
        'spider',
        'scraper',
        'curl',
        'wget',
    ],
],
```

---

## 🔄 Como Usar nas Services

### Acessar via `config()`

```php
// ✅ CORRETO - Acesso via config (não env direto)
$alb_source = config('insights.alb_logs.source');          // 's3'
$batch_size = config('insights.alb_analysis.batch_size');  // 10000
$slo_api = config('insights.sre_targets.API.slo');         // 98.5

// ❌ ERRADO - Não usar env() direto em services
$value = env('ALB_LOG_SOURCE');  // Bad - acoplamento direto
```

### Injetar Configuração

```php
class SREMetricsCalculator {
    public function __construct(private array $config) {}
    
    public function calculate() {
        $slo = $this->config['sre_targets']['API']['slo'];
    }
}

// ServiceProvider
$this->app->bind(SREMetricsCalculator::class, function ($app) {
    return new SREMetricsCalculator(
        config('insights')
    );
});
```

---

## 📝 Exemplos de .env Completo

### Development (`.env.local`)
```bash
# Incident Correlation
INSIGHTS_STORAGE_PATH=/var/www/html/docs/software-management/reliability
AWS_INCIDENT_S3_BUCKET=refresher-logs

# ALB Logs
ALB_LOG_SOURCE=s3
AWS_ALB_LOGS_BUCKET=refresher-logs
AWS_ALB_LOGS_PATH=AWSLogs/624082998591/elasticloadbalancing/us-east-1
```

### Staging/Production (`.env` gerenciado por DevOps)
```bash
# Mesma estrutura, credenciais rotativas via AWS Secrets Manager
# Em produção, nunca armazenar credenciais direto - usar IAM roles
```

---

## ✅ Checklist de Configuração

- [ ] Arquivo `config/insights.php` versionado
- [ ] Arquivo `.env` não versionado (no `.gitignore`)
- [ ] Todas as config paths em `config/insights.php`
- [ ] Apenas bucket names/paths no `.env`
- [ ] Services usam `config()` ao invés de `env()`
- [ ] Documentação de variáveis `.env` em `README.md`
- [ ] SLO/SLA targets centralizados em config
- [ ] Thresholds e análise centralizados em config

---

## 🚀 Migração de Código

Se encontrar código usando `env()` diretamente:

```php
// ANTES (❌ Acoplado ao .env)
$bucket = env('AWS_ALB_LOGS_BUCKET');
$slo = env('SRE_SLO_API', 98.5);

// DEPOIS (✅ Desacoplado via config)
$bucket = config('insights.alb_logs.s3.bucket');
$slo = config('insights.sre_targets.API.slo');
```

---

## 📚 Referências

- [Laravel Configuration - Official Docs](https://laravel.com/docs/10.x/configuration)
- [Twelve-Factor App - Config](https://12factor.net/config)
- [src/Services/Domain/AccessLog/LogParserService.php](../src/Services/Domain/AccessLog/LogParserService.php)
- [src/Services/Infrastructure/S3LogDownloaderService.php](../src/Services/Infrastructure/S3LogDownloaderService.php)

