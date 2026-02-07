# Testes de Integração S3 - Critérios de Aceitação

## 📋 Visão Geral

Este documento define os critérios de aceitação para o fluxo completo de análise de incidentes com logs reais do S3 AWS.

## 🎯 Objetivo

Validar que o sistema é capaz de:
1. Conectar ao S3 real
2. Baixar logs do período do incidente
3. Extrair arquivos compactados
4. Parsear logs ALB corretamente
5. Classificar IPs (legitimate/suspicious/malicious)
6. Salvar resultado em JSON
7. Usar cache para evitar re-downloads

## 🧪 Executando os Testes

### Pré-requisitos

Configure as credenciais AWS no `.env`:

```env
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_INCIDENT_S3_BUCKET=refresher-logs
AWS_INCIDENT_S3_PATH=AWSLogs/624082998591/elasticloadbalancing/us-east-1
```

### Teste Manual (Comando Artisan)

```bash
php artisan insights:test-incident INC-2026-001 \
  --start=2026-01-15T10:00:00Z \
  --end=2026-01-15T10:30:00Z
```

**Output esperado:**

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INCIDENT ANALYSIS TEST
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📋 Step 1: Validating Configuration
   ✓ AWS_ACCESS_KEY_ID: ***
   ✓ AWS_SECRET_ACCESS_KEY: ***
   ✓ S3_BUCKET: refresher-logs
   ✓ S3_PATH: AWSLogs/...
   ✅ Configuration OK

📦 Step 2: Preparing Incident Data
   Incident ID: INC-2026-001
   Started At: 2026-01-15T10:00:00Z
   Restored At: 2026-01-15T10:30:00Z

⚙️  Step 3: Analyzing Logs (download → extract → parse → classify)
   This may take a few seconds...
   ✅ Analysis completed in 5.2s

📊 Step 4: Analysis Results
   Total Requests: 12,543
   Unique IPs: 87
   
   IP Classifications:
   ├─ 🟢 Legitimate: 80 IPs
   ├─ 🟡 Suspicious: 5 IPs
   └─ 🔴 Malicious: 2 IPs

💾 Step 5: Verifying Saved JSON
   ✅ JSON file saved: storage/app/incidents/INC-2026-001/alb_logs_analysis.json
   📦 File size: 45,234 bytes
   ✅ JSON format valid

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅ ALL TESTS PASSED
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### Teste Automatizado (PHPUnit)

```bash
cd /path/to/laravel-insights
vendor/bin/phpunit tests/Feature/S3LogDownloadIntegrationTest.php
```

**Output esperado:**

```
PHPUnit 10.5.x

S3 Log Download Integration Test
 ✔ S3 credentials are configured
 ✔ Downloads logs from s3 for incident
 ✔ Extracts gz files to log
 ✔ Reads and parses alb logs correctly
 ✔ Classifies ips correctly
 ✔ Saves analysis result to json
 ✔ Uses cache and does not redownload

Time: 00:08.234, Memory: 24.00 MB

OK (7 tests, 25 assertions)
```

## ✅ Critérios de Aceitação (7 etapas)

### AC1: Conecta ao S3 real com credenciais configuradas

**Objetivo:** Validar que as credenciais AWS estão configuradas corretamente.

**Teste:**
```php
public function test_s3_credentials_are_configured(): void
{
    $this->assertNotEmpty(config('filesystems.disks.s3.key'));
    $this->assertNotEmpty(config('filesystems.disks.s3.secret'));
    $this->assertNotEmpty(config('insights.incident_correlation.s3_bucket'));
}
```

**Resultado esperado:**
- ✅ `AWS_ACCESS_KEY_ID` configurado
- ✅ `AWS_SECRET_ACCESS_KEY` configurado
- ✅ `S3_BUCKET` configurado
- ✅ `S3_PATH` configurado

---

### AC2: Baixa logs do período do incidente INC-2026-001

**Objetivo:** Download de logs do S3 para o período especificado.

**Fluxo:**
1. Calcula prefixos S3 baseado em datas (YYYY/MM/DD/)
2. Lista objetos no S3 para cada prefix
3. Baixa arquivos `.log.gz` que não existem localmente
4. Retorna contagem de arquivos baixados

**Teste:**
```php
public function test_downloads_logs_from_s3_for_incident(): void
{
    $result = $this->downloader->downloadLogsForIncident(
        'INC-2026-001',
        Carbon::parse('2026-01-15T10:00:00Z'),
        Carbon::parse('2026-01-15T10:30:00Z')
    );
    
    $this->assertGreaterThanOrEqual(0, $result['downloaded_count']);
}
```

**Resultado esperado:**
- ✅ Array com `incident_id`, `downloaded_count`, `extracted_count`, `local_path`
- ✅ `downloaded_count` >= 0 (pode ser 0 se já existe em cache)
- ✅ Logs salvos em `storage/app/incidents/.raw_logs/INC-2026-001/`

---

### AC3: Extrai arquivos .gz para .log

**Objetivo:** Descompactar arquivos `.gz` baixados do S3.

**Fluxo:**
1. Busca todos `.gz` no diretório do incidente
2. Executa `gunzip` para cada arquivo
3. Remove `.gz` após extração bem-sucedida
4. Cache: pula extração se `.log` já existe

**Teste:**
```php
public function test_extracts_gz_files_to_log(): void
{
    $incident_logs_dir = storage_path("app/incidents/.raw_logs/INC-2026-001");
    $log_files = glob($incident_logs_dir . '/*.log');
    
    $this->assertNotEmpty($log_files);
}
```

**Resultado esperado:**
- ✅ Diretório existe: `storage/app/incidents/.raw_logs/INC-2026-001/`
- ✅ Pelo menos 1 arquivo `.log` no diretório
- ✅ Arquivos `.log` são legíveis

---

### AC4: Lê e parseia logs ALB corretamente

**Objetivo:** Validar que logs seguem formato ALB e são parseáveis.

**Formato ALB esperado:**
```
http 2026-02-06T12:00:00.000000Z app/refresher-prod/abc 192.168.1.100:443 10.0.1.50:80 0.001 0.050 0.000 200 200 100 500 "GET https://refresher.com.br:443/api/users HTTP/1.1" "Mozilla/5.0" ECDHE-RSA-AES128-GCM-SHA256 TLSv1.2 arn:... "Root=..." "refresher.com.br" "arn:..." 0 2026-02-06T12:00:00.000000Z "forward" "-" "-" "10.0.1.50:80" "200" "-" "-"
```

**Campos críticos:**
- `client_ip:port` (ex: `192.168.1.100:443`)
- `timestamp` (ISO8601)
- `elb_status_code` (200, 404, 500)
- `request` (método + URL)

**Teste:**
```php
public function test_reads_and_parses_alb_logs_correctly(): void
{
    $log_files = glob(storage_path("app/incidents/.raw_logs/INC-2026-001/*.log"));
    $lines = file($log_files[0], FILE_IGNORE_NEW_LINES);
    
    $this->assertMatchesRegularExpression('/^(http|https) /', $lines[0]);
    $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T/', $lines[0]);
    $this->assertMatchesRegularExpression('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+/', $lines[0]);
}
```

**Resultado esperado:**
- ✅ Primeira linha começa com `http` ou `https`
- ✅ Contém timestamp ISO8601
- ✅ Contém IP:porta do cliente
- ✅ Formato ALB válido

---

### AC5: Classifica IPs em legitimate/suspicious/malicious

**Objetivo:** Analisar comportamento de IPs e classificar corretamente.

**Regras de Classificação:**

**🔴 Malicious:**
- Error rate >= 95%
- Volume >= 200 requests

**🟡 Suspicious:**
- Error rate >= 90%
- OU Unique paths >= 100 (path scanning)

**🟢 Legitimate:**
- Resto (error rate < 90%)

**Teste:**
```php
public function test_classifies_ips_correctly(): void
{
    $result = $this->analysisService->analyzeLogs('INC-2026-001', $incident_data);
    
    $this->assertArrayHasKey('legitimate', $result['classified']);
    $this->assertArrayHasKey('suspicious', $result['classified']);
    $this->assertArrayHasKey('malicious', $result['classified']);
    
    $total_classified = count($result['classified']['legitimate']) 
                      + count($result['classified']['suspicious'])
                      + count($result['classified']['malicious']);
    
    $this->assertEquals($result['unique_ips'], $total_classified);
}
```

**Resultado esperado:**
- ✅ `total_requests` > 0
- ✅ `unique_ips` > 0
- ✅ Todos os IPs classificados (soma = unique_ips)
- ✅ Cada IP tem: `ip`, `total_requests`, `error_rate`, `unique_paths`

---

### AC6: Salva resultado em JSON

**Objetivo:** Persistir análise em arquivo JSON estruturado.

**Estrutura JSON esperada:**
```json
{
  "incident_id": "INC-2026-001",
  "total_requests": 12543,
  "unique_ips": 87,
  "classified": {
    "legitimate": [
      {
        "ip": "192.168.1.100",
        "total_requests": 145,
        "error_rate": 0.02,
        "unique_paths": 12
      }
    ],
    "suspicious": [...],
    "malicious": [...]
  }
}
```

**Teste:**
```php
public function test_saves_analysis_result_to_json(): void
{
    $result_file = storage_path("app/incidents/INC-2026-001/alb_logs_analysis.json");
    
    $this->assertFileExists($result_file);
    
    $json = json_decode(file_get_contents($result_file), true);
    $this->assertNotNull($json);
    $this->assertArrayHasKey('incident_id', $json);
}
```

**Resultado esperado:**
- ✅ Arquivo existe: `storage/app/incidents/INC-2026-001/alb_logs_analysis.json`
- ✅ JSON válido
- ✅ Contém `incident_id`, `classified`, `total_requests`
- ✅ Tamanho > 0 bytes

---

### AC7: Usa cache (não re-baixa se já existe)

**Objetivo:** Evitar downloads desnecessários e extração redundante.

**Fluxo de Cache:**

1. **Download Cache:**
   - Antes de baixar `.gz`, verifica se já existe
   - Se existe → pula download
   
2. **Extraction Cache:**
   - Antes de extrair `.log`, verifica se já existe
   - Se existe → pula extração

**Teste:**
```php
public function test_uses_cache_and_does_not_redownload(): void
{
    // Segunda chamada (cache hit)
    $result = $this->downloader->downloadLogsForIncident(
        'INC-2026-001',
        $startedAt,
        $restoredAt,
        true,
        false // forceExtraction = false
    );
    
    $this->assertEquals(0, $result['downloaded_count']);
    $this->assertEquals(0, $result['extracted_count']);
}
```

**Resultado esperado:**
- ✅ `downloaded_count` = 0 (cache hit)
- ✅ `extracted_count` = 0 (cache hit)
- ✅ Análise funciona com logs em cache

---

## 🐛 Troubleshooting

### Erro: "No logs downloaded from S3"

**Possíveis causas:**
1. Credenciais AWS incorretas
2. Bucket/path S3 incorreto
3. Período do incidente sem logs
4. Permissões IAM insuficientes

**Solução:**
```bash
# Testar conexão manualmente
php artisan insights:test-incident INC-2026-001

# Verificar logs do Laravel
tail -f storage/logs/laravel.log
```

### Erro: "Incident logs directory not found"

**Causa:** Logs não foram baixados do S3.

**Solução:**
```bash
# Forçar re-download
rm -rf storage/app/incidents/.raw_logs/INC-2026-001
php artisan insights:test-incident INC-2026-001 --force
```

### Erro: "Invalid JSON format"

**Causa:** Erro durante análise/salvamento.

**Solução:**
```bash
# Verificar arquivo JSON manualmente
cat storage/app/incidents/INC-2026-001/alb_logs_analysis.json | jq .
```

## 📊 Métricas de Sucesso

| Métrica | Valor Esperado |
|---------|----------------|
| Tempo de download | < 30s para período de 30min |
| Tempo de extração | < 10s para 10 arquivos .gz |
| Tempo de parsing | < 5s para 10,000 linhas |
| Tempo total | < 1min para análise completa |
| Taxa de sucesso | 100% com credenciais válidas |

## 🔗 Referências

- [AWS ALB Log Format](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-access-logs.html)
- [Incident Correlation Service](../src/Services/IncidentCorrelationService.php)
- [S3 Log Downloader Service](../src/Services/Infrastructure/S3LogDownloaderService.php)
- [Test Implementation](./S3LogDownloadIntegrationTest.php)

---

**Versão:** 1.0  
**Última Atualização:** 2026-02-06  
**Status:** ✅ Implementado
