<?php

namespace MatheusFS\Laravel\Insights\Services\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

/**
 * S3LogDownloaderService - Download e extração de logs do ALB no S3
 *
 * Responsabilidade: Buscar logs da AWS S3, descompactar e organizar
 * Lógica de infraestrutura, sem I/O de banco de dados
 */
class S3LogDownloaderService
{
    private string $s3Bucket;
    private string $s3Path;
    private string $localBasePath;

    public function __construct()
    {
        $this->s3Bucket = config('insights.alb_logs.s3.bucket', 'refresher-logs');
        $this->s3Path = config('insights.alb_logs.s3.path', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1');
        
        // DIRETÓRIO UNIFICADO para TODOS os logs de acesso (compartilhado entre incidentes e comandos)
        $this->localBasePath = config('insights.access_logs_path', storage_path('insights/access-logs'));

        // Garantir que diretório base existe
        if (!File::isDirectory($this->localBasePath)) {
            File::makeDirectory($this->localBasePath, 0755, true);
        }
    }

    /**
     * Baixa logs do S3 para um incidente específico
     *
     * Carrega o incidente do JSON, extrai timestamps e busca logs do período.
     * Filtra arquivos por timestamp no nome (formato ALB: YYYYMMDDTHHmmZ)
     *
     * @param  string  $incidentId  ID do incidente (ex: INC-2026-001)
     * @param  bool    $useMargins  Se deve usar margem de 1h antes/depois dos timestamps
     * @param  bool    $forceExtraction  Se deve forçar re-extração mesmo com .log existente
     * @return array Array com caminho local e quantidade de logs baixados
     * @throws \Exception Se incidente não for encontrado
     */
    public function downloadLogsForIncident(
        string $incidentId,
        bool $useMargins = true,
        bool $forceExtraction = false
    ): array {
        // Carregar incidente do JSON para obter timestamps reais
        $incidentData = $this->loadIncident($incidentId);
        
        // Extrair timestamps do incidente (formato ISO8601)
        $startedAt = Carbon::parse($incidentData['started_at'] ?? $incidentData['timestamp']['started_at']);
        $restoredAt = Carbon::parse($incidentData['restored_at'] ?? $incidentData['timestamp']['restored_at']);

        // Usar diretório unificado (todos os logs vão para o mesmo lugar)
        $logsPath = $this->localBasePath;

        // Determinar período de logs a buscar
        // Para incidentes: margem de 1 hora antes/depois (contexto)
        // Para SRE metrics: período exato (datas já em UTC)
        if ($useMargins) {
            $searchStartDate = $startedAt->clone()->subHour();
            $searchEndDate = $restoredAt->clone()->addHour();
        } else {
            $searchStartDate = $startedAt->clone();
            $searchEndDate = $restoredAt->clone();
        }

        // Gerar lista de prefixos S3 para as datas (YYYY/MM/DD/)
        $s3Prefixes = $this->generateS3Prefixes($searchStartDate, $searchEndDate);

        $downloadedCount = 0;
        $totalPrefixes = count($s3Prefixes);
        
        \Log::info("Starting S3 download for {$incidentId}", [
            'period' => "{$searchStartDate->toDateString()} to {$searchEndDate->toDateString()}",
            'timestamps' => [
                'started_at' => $startedAt->toIso8601String(),
                'restored_at' => $restoredAt->toIso8601String(),
            ],
            'prefixes_to_download' => $totalPrefixes,
            'prefixes' => $s3Prefixes,
        ]);

        // Baixar logs de cada prefix COM FILTRAGEM POR TIMESTAMP
        foreach ($s3Prefixes as $index => $prefix) {
            $currentIndex = $index + 1;
            \Log::info("Downloading from S3 prefix [{$currentIndex}/{$totalPrefixes}]: {$prefix}");
            
            // Passar timestamps para filtrar no nome do arquivo
            $logsInPrefix = $this->downloadLogsFromPrefix(
                $prefix, 
                $logsPath,
                $searchStartDate, // Filtro: >= este timestamp
                $searchEndDate    // Filtro: <= este timestamp
            );
            $downloadedCount += $logsInPrefix;
            
            \Log::info("Downloaded {$logsInPrefix} files from {$prefix}");
        }

        \Log::info("Total files downloaded: {$downloadedCount}");
        
        // Descompactar todos os .gz
        \Log::info("Starting extraction of .gz files...");
        $extractedCount = $this->extractGzFiles($logsPath, $forceExtraction);
        \Log::info("Extracted {$extractedCount} files");

        return [
            'incident_id' => $incidentId,
            'local_path' => $logsPath,
            'downloaded_count' => $downloadedCount,
            'extracted_count' => $extractedCount,
            'period' => [
                'started_at' => $startedAt->toIso8601String(),
                'restored_at' => $restoredAt->toIso8601String(),
            ],
        ];
    }

    /**
     * Gera lista de prefixos S3 para um período de datas
     *
     * IMPORTANTE: Converte para UTC antes de gerar prefixos pois ALB grava logs em UTC
     * Gera prefixos dos DIAS que os timestamps tocam (não dias inteiros)
     * Filtragem precisa por timestamp acontece depois no downloadLogsFromPrefix()
     *
     * @param  Carbon  $startDate Data início (qualquer timezone)
     * @param  Carbon  $endDate Data fim (qualquer timezone)
     * @return array Lista de prefixos (YYYY/MM/DD/)
     */
    private function generateS3Prefixes(Carbon $startDate, Carbon $endDate): array
    {
        $prefixes = [];
        
        // Converter para UTC pois ALB grava logs em UTC
        // Pegar apenas os DIAS que os timestamps tocam
        $startDay = $startDate->clone()->setTimezone('UTC')->startOfDay();
        $endDay = $endDate->clone()->setTimezone('UTC')->startOfDay();

        $current = $startDay->clone();

        // Incluir ambos os dias (start e end) usando lte()
        while ($current->lte($endDay)) {
            $prefix = sprintf(
                '%d/%02d/%02d/',
                $current->year,
                $current->month,
                $current->day
            );

            $prefixes[] = $prefix;
            $current->addDay();
        }

        return $prefixes;
    }

    /**
     * Baixa logs do S3 de um prefixo específico
     *
     * OTIMIZAÇÕES:
     * - Paginação com MaxKeys=100 para listar rapidamente
     * - Timeout de 5s por request (evita travamentos)
     * - Pula download se arquivo já existe (cache)
     * - FILTRA por timestamp no nome do arquivo (formato ALB: YYYYMMDDTHHmmZ)
     *
     * @param  string  $prefix      Prefixo S3 (YYYY/MM/DD/)
     * @param  string  $localPath   Caminho local para salvar
     * @param  Carbon|null  $startTime  Timestamp mínimo (filtro)
     * @param  Carbon|null  $endTime    Timestamp máximo (filtro)
     * @return int Quantidade de arquivos baixados
     */
    private function downloadLogsFromPrefix(
        string $prefix, 
        string $localPath, 
        ?Carbon $startTime = null, 
        ?Carbon $endTime = null
    ): int
    {
        $s3Path = $this->s3Path . '/' . $prefix;

        \Log::info("Attempting to download from S3", [
            'bucket' => $this->s3Bucket,
            'prefix' => $s3Path,
            'local_path' => $localPath,
        ]);

        try {
            // Usar AWS SDK S3Client com timeout configurado
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => config('insights.alb_logs.s3.region', 'us-east-1'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
                'http' => [
                    'timeout' => 5, // 5s timeout por request (evita travar)
                    'connect_timeout' => 3, // 3s para conectar
                ],
            ]);

            $count = 0;
            $totalObjects = 0;
            $matchedGz = 0;
            $sampleKeys = [];
            $continuationToken = null;

            // Usar paginação para listar objetos rapidamente
            do {
                $params = [
                    'Bucket' => $this->s3Bucket,
                    'Prefix' => $s3Path,
                    'MaxKeys' => 100, // Listar 100 por vez (mais rápido que 1000)
                ];

                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $s3Client->listObjectsV2($params);

                if (!isset($result['Contents'])) {
                    break;
                }

                $totalObjects += count($result['Contents']);

                // Processar objetos desta página
                foreach ($result['Contents'] as $object) {
                    $key = $object['Key'];
                    if (count($sampleKeys) < 3) {
                        $sampleKeys[] = $key;
                    }
                    
                    if (str_ends_with($key, '.log.gz') || str_ends_with($key, '.gz')) {
                        $matchedGz++;
                        $filename = basename($key);
                        
                        // FILTRO POR TIMESTAMP: Extrair timestamp do nome do arquivo
                        // Formato: 624082998591_elasticloadbalancing_us-east-1_app.production.xxx_20260202T2225Z_*.log.gz
                        // Extrair: 20260202T2225Z (posição 4 quando split por _)
                        if ($startTime || $endTime) {
                            $fileTimestamp = $this->extractTimestampFromFilename($filename);
                            
                            if ($fileTimestamp) {
                                // Converter timestamp do arquivo para Carbon
                                $fileTime = Carbon::createFromFormat('Ymd\THi\Z', $fileTimestamp, 'UTC');
                                
                                // Filtrar: pular se fora do período
                                if ($startTime && $fileTime->lt($startTime)) {
                                    continue; // Arquivo muito antigo
                                }
                                if ($endTime && $fileTime->gt($endTime)) {
                                    continue; // Arquivo muito recente
                                }
                            }
                        }
                        
                        $localFile = $localPath . '/' . $filename;

                        // Cache: não baixar se já existe
                        if (File::exists($localFile)) {
                            continue;
                        }

                        // Baixar arquivo
                        try {
                            $objectResult = $s3Client->getObject([
                                'Bucket' => $this->s3Bucket,
                                'Key' => $key,
                            ]);
                            
                            File::put($localFile, $objectResult['Body']->getContents());
                            $count++;
                        } catch (\Exception $downloadEx) {
                            \Log::warning("Failed to download {$key}: {$downloadEx->getMessage()}");
                        }
                    }
                }

                // Próxima página
                $continuationToken = $result['NextContinuationToken'] ?? null;

            } while ($continuationToken);

            \Log::info("Found objects in S3", [
                'total_objects' => $totalObjects,
                'matched_gz_files' => $matchedGz,
                'downloaded_gz_files' => $count,
            ]);

            if ($totalObjects > 0 && $matchedGz === 0) {
                \Log::warning("No .gz log files matched in S3 prefix", [
                    'prefix' => $s3Path,
                    'sample_keys' => $sampleKeys,
                ]);
            }

            return $count;
        } catch (\Exception $e) {
            \Log::warning("Failed to download logs from S3 prefix {$s3Path}: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Extrai todos os arquivos .gz em um diretório
     *
     * Implementa cache de extração: se .log já existe, pula a extração
     * exceto quando $forceExtraction=true (para casos de re-processamento).
     *
     * Lógica de Eficiência:
     * - Sem --force: Pula .gz download (se existe) + pula .log extraction (se existe)
     *   → Resultado: S3 API economizada + CPU economizada ✅
     * - Com --force: Re-baixa .gz do S3 + re-extrai .log
     *   → Resultado: Re-processa desde a origem (force refresh) ✅
     *
     * @param  string  $dirPath  Caminho do diretório
     * @param  bool    $forceExtraction  Se true, extrai mesmo com .log existente
     * @return int Quantidade de arquivos extraídos
     */
    private function extractGzFiles(string $dirPath, bool $forceExtraction = false): int
    {
        $count = 0;
        $skipped = 0;

        $gzFiles = glob($dirPath . '/*.gz');
        foreach ($gzFiles as $gzFile) {
            try {
                $outputFile = substr($gzFile, 0, -3); // Remove .gz

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
                \Log::warning("Failed to extract {$gzFile}: {$e->getMessage()}");
            }
        }

        if ($skipped > 0) {
            \Log::info("Extraction cache: skipped {$skipped} .log files already extracted");
        }

        return $count;
    }

    /**
     * Baixa logs para um período arbitrário (comando ALB logs)
     *
     * Utiliza o MESMO diretório unificado, permitindo reutilização de logs
     * já baixados quando há intersecção com períodos de incidentes.
     *
     * @param  Carbon  $startDate  Data/hora de início
     * @param  Carbon  $endDate    Data/hora de fim
     * @param  bool    $forceExtraction  Se deve forçar re-extração
     * @return array Array com período, quantidade de logs baixados e extraídos
     */
    public function downloadLogsForPeriod(
        Carbon $startDate,
        Carbon $endDate,
        bool $forceExtraction = false
    ): array {
        $logsPath = $this->localBasePath;

        $searchStartDate = $startDate->clone();
        $searchEndDate = $endDate->clone();

        // Gerar lista de prefixos S3 para o período
        $s3Prefixes = $this->generateS3Prefixes($searchStartDate, $searchEndDate);

        $downloadedCount = 0;
        $totalPrefixes = count($s3Prefixes);
        
        \Log::info("Starting S3 download for period", [
            'period' => "{$searchStartDate->toDateString()} to {$searchEndDate->toDateString()}",
            'prefixes_to_download' => $totalPrefixes,
        ]);

        // Baixar logs de cada prefix COM FILTRAGEM POR TIMESTAMP
        foreach ($s3Prefixes as $index => $prefix) {
            $currentIndex = $index + 1;
            \Log::info("Downloading from S3 prefix [{$currentIndex}/{$totalPrefixes}]: {$prefix}");
            
            // Passar timestamps para filtrar no nome do arquivo
            $logsInPrefix = $this->downloadLogsFromPrefix(
                $prefix, 
                $logsPath,
                $searchStartDate, // Filtro: >= este timestamp
                $searchEndDate    // Filtro: <= este timestamp
            );
            $downloadedCount += $logsInPrefix;
            
            \Log::info("Downloaded {$logsInPrefix} files from {$prefix}");
        }

        \Log::info("Total files downloaded: {$downloadedCount}");
        
        // Descompactar todos os .gz
        \Log::info("Starting extraction of .gz files...");
        $extractedCount = $this->extractGzFiles($logsPath, $forceExtraction);
        \Log::info("Extracted {$extractedCount} files");

        return [
            'local_path' => $logsPath,
            'downloaded_count' => $downloadedCount,
            'extracted_count' => $extractedCount,
            'period' => [
                'started_at' => $startDate->toIso8601String(),
                'ended_at' => $endDate->toIso8601String(),
            ],
        ];
    }

    /**
     * Verifica se há logs disponíveis para um período
     *
     * Busca por arquivos .log que existem no diretório unificado.
     * Não valida as datas dos logs, apenas verifica existência.
     *
     * @return bool True se há arquivos .log no diretório
     */
    public function hasAvailableLogs(): bool
    {
        if (!File::isDirectory($this->localBasePath)) {
            return false;
        }

        $logFiles = glob($this->localBasePath . '/*.log');
        return count($logFiles) > 0;
    }

    /**
     * Retorna a data do log mais antigo e mais recente disponível
     *
     * Tenta extrair datas do nome dos arquivos ou ler timestamps de log.
     * Útil para entender que períodos de logs estão disponíveis.
     *
     * @return array ['oldest' => Carbon, 'newest' => Carbon] ou null se sem logs
     */
    public function getAvailableLogsDateRange(): ?array
    {
        if (!$this->hasAvailableLogs()) {
            return null;
        }

        $logFiles = glob($this->localBasePath . '/*.log');
        $dates = [];

        foreach ($logFiles as $logFile) {
            // Tentar extrair data do nome (formato: alb_logs_2026_02_05_00.log)
            if (preg_match('/(\d{4})_(\d{2})_(\d{2})_(\d{2})/', $logFile, $matches)) {
                $date = Carbon::create($matches[1], $matches[2], $matches[3], $matches[4]);
                $dates[] = $date;
            }
        }

        if (empty($dates)) {
            return null;
        }

        return [
            'oldest' => min($dates),
            'newest' => max($dates),
        ];
    }

    /**
     * Lista logs disponíveis no diretório unificado
     *
     * IMPORTANTE: Todos os logs são baixados para o MESMO diretório unificado (access_logs_path),
     * não há subdiretórios por incidente. Isso permite reutilizar logs já baixados quando
     * há intersecção de períodos entre diferentes incidentes ou comandos.
     *
     * @param  string  $incidentId  ID do incidente (não usado, mantido para compatibilidade de API)
     * @return array Array com caminhos dos arquivos .log do diretório unificado
     */
    public function listLogsForIncident(string $incidentId): array
    {
        if (!File::isDirectory($this->localBasePath)) {
            return [];
        }

        return glob($this->localBasePath . '/*.log');
    }

    /**
     * Carrega dados do incidente do JSON consolidado
     *
     * @param  string  $incidentId  ID do incidente (ex: INC-2026-001)
     * @return array Dados do incidente com started_at e restored_at
     * @throws \Exception Se incidente não for encontrado
     */
    private function loadIncident(string $incidentId): array
    {
        $incidentsPath = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
        // O arquivo consolidado está no diretório parent: storage/insights/reliability/incidents.json
        $parentPath = dirname($incidentsPath);
        $incidentsFile = $parentPath . '/incidents.json';

        if (!File::exists($incidentsFile)) {
            throw new \Exception("Incidents file not found: {$incidentsFile}");
        }

        $incidentsJson = File::get($incidentsFile);
        $allIncidents = json_decode($incidentsJson, true);

        if (!$allIncidents) {
            throw new \Exception("Invalid incidents JSON: {$incidentsFile}");
        }

        // Procurar o incidente específico
        $incidentsArray = $allIncidents['incidents'] ?? [];
        $incidentData = null;

        foreach ($incidentsArray as $incident) {
            if ($incident['id'] === $incidentId) {
                $incidentData = $incident;
                break;
            }
        }

        if (!$incidentData) {
            throw new \Exception("Incident not found: {$incidentId}");
        }

        // Validar que tem os timestamps necessários
        if (!isset($incidentData['started_at']) && !isset($incidentData['timestamp']['started_at'])) {
            throw new \Exception("Incident missing started_at timestamp: {$incidentId}");
        }

        if (!isset($incidentData['restored_at']) && !isset($incidentData['timestamp']['restored_at'])) {
            throw new \Exception("Incident missing restored_at timestamp: {$incidentId}");
        }

        return $incidentData;
    }

    /**
     * Extrai timestamp do nome do arquivo ALB
     * 
     * Formato: 624082998591_elasticloadbalancing_us-east-1_app.production.xxx_20260202T2225Z_54.85.26.63_xyz.log.gz
     * Retorna: 20260202T2225Z (formato YYYYMMDDTHHmmZ)
     * 
     * @param string $filename Nome do arquivo
     * @return string|null Timestamp extraído ou null se não encontrado
     */
    private function extractTimestampFromFilename(string $filename): ?string
    {
        // Pattern ALB: {account}_{service}_{region}_{loadbalancer}_{timestamp}_{ip}_{random}.log.gz
        // Split por _ e pegar posição 4 (índice 4)
        $parts = explode('_', $filename);
        
        // O timestamp está na posição 4 (após account, service, region, loadbalancer)
        if (isset($parts[4]) && preg_match('/^\d{8}T\d{4}Z$/', $parts[4])) {
            return $parts[4];
        }
        
        return null;
    }

    /**
     * Lista todos os arquivos (tanto .log quanto .gz) no diretório unificado
     *
     * Útil para auditar quais logs estão disponíveis e em que estado
     * (.log = extraído, .gz = ainda compactado).
     *
     * @return array ['extracted' => [...], 'compressed' => [...]]
     */
    public function listAllFiles(): array
    {
        if (!File::isDirectory($this->localBasePath)) {
            return ['extracted' => [], 'compressed' => []];
        }

        return [
            'extracted' => glob($this->localBasePath . '/*.log') ?: [],
            'compressed' => glob($this->localBasePath . '/*.gz') ?: [],
        ];
    }

    /**
     * Lista arquivos .log extraídos no diretório unificado
     * 
     * @return array Lista de caminhos absolutos para arquivos .log
     */
    public function listExtractedLogs(): array
    {
        if (!File::isDirectory($this->localBasePath)) {
            return [];
        }

        return glob($this->localBasePath . '/*.log') ?: [];
    }
}
