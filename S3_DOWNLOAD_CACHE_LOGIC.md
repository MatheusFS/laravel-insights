# S3 Log Download: Lógica de Cache com Force Flag

## Resumo

Downloads de log do S3 acontecem **APENAS SE**:
1. **NÃO existem** arquivos `.log` ou `.log.gz` localmente (primeira vez)
2. **Existem arquivos** MAS `forceExtraction=true` (re-download forçado)

## Fluxo Lógico

```
downloadLogsForIncident(incidentId, useMargins, forceExtraction)
│
├─ Verificar: Existem .log ou .log.gz localmente?
│  │
│  ├─ SIM, forceExtraction=false
│  │  └─→ [SKIP] Retorna skipped=true
│  │      (economiza S3 API + CPU)
│  │
│  ├─ SIM, forceExtraction=true
│  │  └─→ [PROCEED] Log de "Force extraction enabled"
│  │      Continua para re-download do S3
│  │
│  └─ NÃO (primeira vez)
│     └─→ [PROCEED] Continua normalmente
│         Baixa do S3
│
├─ Para cada prefixo S3 (YYYY/MM/DD/):
│  │
│  ├─ Listar objetos com timeout 5s
│  ├─ Filtrar por timestamp no nome do arquivo
│  │
│  └─ Para cada arquivo .gz:
│     │
│     ├─ Existe localmente E forceExtraction=false?
│     │  └─→ [SKIP] Não re-baixa
│     │
│     ├─ Existe localmente E forceExtraction=true?
│     │  └─→ [DOWNLOAD] Re-baixa sobrescrevendo
│     │
│     └─ Não existe?
│        └─→ [DOWNLOAD] Baixa normalmente
│
├─ Extrair todos os .gz:
│  │
│  ├─ Existe .log E forceExtraction=false?
│  │  └─→ [SKIP] Não re-extrai
│  │
│  └─ Existe .log E forceExtraction=true?
│     └─→ [EXTRACT] Re-extrai sobrescrevendo
│
└─ Retornar {downloaded_count, extracted_count, skipped}
```

## Cenários

### Cenário 1: Primeira Execução (Cache Miss)

```
Estado: Diretório /access-logs VAZIO

Comando:
  $ ./download-logs INC-2026-001

Execução:
  ✅ hasLocalLogFiles('/access-logs') → false
  ✅ Continua para S3 download
  ✅ Baixa todos os .log.gz necessários
  ✅ Extrai todos os .log
  
Resultado:
  - downloaded_count: 150
  - extracted_count: 150
  - skipped: false
```

### Cenário 2: Segunda Execução (Cache Hit)

```
Estado: Diretório /access-logs TEM 150 arquivos .log

Comando:
  $ ./download-logs INC-2026-001

Execução:
  ✅ hasLocalLogFiles('/access-logs') → true
  ✅ forceExtraction=false (padrão)
  ✅ Retorna imediatamente com skipped=true
  
Resultado:
  - downloaded_count: 0
  - extracted_count: 0
  - skipped: true
  - reason: "Local files already exist. Use --force to re-download."

Economia:
  - S3 API calls: 0 (nenhuma chamada)
  - Tempo: ~10ms (apenas check de arquivo)
```

### Cenário 3: Re-download Forçado

```
Estado: Diretório /access-logs TEM 150 arquivos .log
Problema: Logs foram corrompidos ou precisam ser re-processados

Comando:
  $ ./download-logs INC-2026-001 --force

Execução:
  ✅ hasLocalLogFiles('/access-logs') → true
  ✅ forceExtraction=true
  ✅ Log: "Force extraction enabled - re-downloading S3 logs"
  ✅ Para cada arquivo S3:
     - Verifica: existe .gz.log localmente?
       - SIM → sobrescreve (forceDownload=true)
       - NÃO → baixa normalmente
  ✅ Para cada extração:
     - Verifica: existe .log localmente?
       - SIM → re-extrai (forceExtraction=true)
       - NÃO → extrai normalmente
  
Resultado:
  - downloaded_count: 150 (todos re-baixados)
  - extracted_count: 150 (todos re-extraídos)
  - skipped: false
  
Custo:
  - S3 API calls: ~2000 (150 files × ~13 API calls)
  - Tempo: ~3-5 minutos
  - CPU: Alto (extração gzip)
```

## Implementação

### Método: `hasLocalLogFiles(string $dirPath): bool`

```php
private function hasLocalLogFiles(string $dirPath): bool
{
    if (!File::isDirectory($dirPath)) {
        return false;  // Diretório não existe
    }

    // Procurar por .log ou .log.gz
    $logFiles = glob($dirPath . '/*.log');
    $gzFiles = glob($dirPath . '/*.log.gz');

    return (count($logFiles) > 0 || count($gzFiles) > 0);
}
```

### Método: `countLocalLogFiles(string $dirPath): int`

```php
private function countLocalLogFiles(string $dirPath): int
{
    if (!File::isDirectory($dirPath)) {
        return 0;
    }

    $logFiles = glob($dirPath . '/*.log');
    $gzFiles = glob($dirPath . '/*.log.gz');

    return count($logFiles) + count($gzFiles);
}
```

### Lógica em `downloadLogsForIncident()`

```php
// Verificar arquivos locais
$hasLocalFiles = $this->hasLocalLogFiles($logsPath);

if ($hasLocalFiles && !$forceExtraction) {
    // Cache hit: pula tudo
    return [
        'incident_id' => $incidentId,
        'local_path' => $logsPath,
        'downloaded_count' => 0,
        'extracted_count' => 0,
        'skipped' => true,
        'reason' => 'Local files already exist. Use --force to re-download.',
        'available_files' => $this->countLocalLogFiles($logsPath),
    ];
}

// Se chegou aqui, continua o download...
```

### Lógica em `downloadLogsFromPrefix()`

```php
private function downloadLogsFromPrefix(
    string $prefix,
    string $localPath,
    ?Carbon $startTime = null,
    ?Carbon $endTime = null,
    bool $forceDownload = false  // ← NOVO PARÂMETRO
): int {
    // ... listar objetos S3 ...
    
    $localFile = $localPath . '/' . $filename;
    
    // Cache: não baixar se já existe EXCETO com forceDownload=true
    if (File::exists($localFile) && !$forceDownload) {
        continue;  // Pula, arquivo já existe
    }
    
    // Baixar arquivo (se não existe OU force=true)
    // ...
}
```

### Lógica em `extractGzFiles()`

```php
private function extractGzFiles(string $dirPath, bool $forceExtraction = false): int
{
    $count = 0;
    $skipped = 0;

    $gzFiles = glob($dirPath . '/*.gz');
    foreach ($gzFiles as $gzFile) {
        try {
            $outputFile = substr($gzFile, 0, -3);  // Remove .gz

            // Cache: pula extração se .log já existe, exceto com --force
            if (File::exists($outputFile) && !$forceExtraction) {
                $skipped++;
                continue;
            }

            // Executar gunzip
            exec("gunzip -f " . escapeshellarg($gzFile), $output, $returnCode);

            if ($returnCode === 0) {
                $count++;
            }
        } catch (\Exception $e) {
            // ...
        }
    }
    
    return $count;
}
```

## Testes

**File:** `tests/Feature/S3LogDownloaderCacheLogicTest.php`

Validações:
1. ✅ Baixa quando não tem arquivos locais
2. ✅ Pula quando tem arquivos e forceExtraction=false
3. ✅ Força re-download quando tem arquivos e forceExtraction=true
4. ✅ Reconhece .log e .log.gz

## Benefícios

| Aspecto | Benefit |
|---------|---------|
| **S3 API Calls** | ↓ 99% em cache hits (nenhuma chamada) |
| **Bandwidth** | ↓ 99% em cache hits (reutiliza downloads) |
| **Time** | ↓ ~100x (10ms vs 3-5 min) |
| **CPU** | ↓ 99% em cache hits (sem extração) |
| **Cost** | ↓ Significante (evita S3 transfers) |
| **Reliability** | ↑ Force flag permite re-processamento |
| **Transparency** | ✅ Retorna skipped=true quando cache hit |

## Exemplo de Uso

```php
// Primeira execução: baixa do S3
$result = $downloader->downloadLogsForIncident('INC-2026-001');
// → downloaded_count: 150, extracted_count: 150

// Segunda execução: usa cache
$result = $downloader->downloadLogsForIncident('INC-2026-001');
// → skipped: true, available_files: 150

// Re-download forçado
$result = $downloader->downloadLogsForIncident('INC-2026-001', true, true);
// → downloaded_count: 150, extracted_count: 150
```

---

**Status:** ✅ Implementado  
**Validação:** ✅ Syntax check passou  
**Tests:** ✅ Criados (S3LogDownloaderCacheLogicTest.php)
