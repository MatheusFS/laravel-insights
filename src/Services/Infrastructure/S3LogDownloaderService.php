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
        // Priorizar config de 's3_logs' (para SRE metrics), fallback para 'incident_correlation'
        $this->s3Bucket = config('insights.s3_logs.bucket') 
            ?? config('insights.incident_correlation.s3_bucket', 'refresher-logs');
        
        $this->s3Path = config('insights.s3_logs.path') 
            ?? config('insights.incident_correlation.s3_path', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1');
        
        $this->localBasePath = config('insights.sre_metrics_path', storage_path('app/sre_metrics')) . '/.raw_logs';

        // Garantir que diretório base existe
        if (!File::isDirectory($this->localBasePath)) {
            File::makeDirectory($this->localBasePath, 0755, true);
        }
    }

    /**
     * Baixa logs do S3 para um incidente específico
     *
     * Busca logs entre as datas de início e restauração, filtra apenas
     * os que correspondem ao período do incidente.
     *
     * @param  string  $incidentId  ID do incidente (ex: INC-2026-001)
     * @param  Carbon  $startedAt   Data/hora de início do incidente
     * @param  Carbon  $restoredAt  Data/hora de restauração
     * @return array Array com caminho local e quantidade de logs baixados
     */
    public function downloadLogsForIncident(
        string $incidentId,
        Carbon $startedAt,
        Carbon $restoredAt,
        bool $useMargins = true
    ): array {
        // Criar pasta específica para o incidente
        $incidentPath = $this->localBasePath . '/' . $incidentId;
        if (!File::isDirectory($incidentPath)) {
            File::makeDirectory($incidentPath, 0755, true);
        }

        // Determinar período de logs a buscar
        // Para incidentes: margem de 1 hora antes/depois
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
            'prefixes_to_download' => $totalPrefixes,
            'prefixes' => $s3Prefixes,
        ]);

        // Baixar logs de cada prefix
        foreach ($s3Prefixes as $index => $prefix) {
            $currentIndex = $index + 1;
            \Log::info("Downloading from S3 prefix [{$currentIndex}/{$totalPrefixes}]: {$prefix}");
            
            $logsInPrefix = $this->downloadLogsFromPrefix($prefix, $incidentPath);
            $downloadedCount += $logsInPrefix;
            
            \Log::info("Downloaded {$logsInPrefix} files from {$prefix}");
        }

        \Log::info("Total files downloaded: {$downloadedCount}");
        
        // Descompactar todos os .gz
        \Log::info("Starting extraction of .gz files...");
        $extractedCount = $this->extractGzFiles($incidentPath);
        \Log::info("Extracted {$extractedCount} files");

        return [
            'incident_id' => $incidentId,
            'local_path' => $incidentPath,
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
     * Itera por DIAS (não horas) para evitar prefixos duplicados
     * 
     * Nota: $endDate = startOfDay(proxDay), então usa lt() ao invés de lte()
     *
     * @param  Carbon  $startDate Data início (qualquer timezone)
     * @param  Carbon  $endDate Data fim (qualquer timezone)
     * @return array Lista de prefixos (YYYY/MM/DD/)
     */
    private function generateS3Prefixes(Carbon $startDate, Carbon $endDate): array
    {
        $prefixes = [];
        
        // Converter para UTC pois ALB grava logs em UTC
        // Usar startOfDay para ambas
        $current = $startDate->clone()->setTimezone('UTC')->startOfDay();
        $end = $endDate->clone()->setTimezone('UTC')->startOfDay();

        // Usar lt() pois end = startOfDay do próximo dia
        while ($current->lt($end)) {
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
     * @param  string  $prefix      Prefixo S3 (YYYY/MM/DD/)
     * @param  string  $localPath   Caminho local para salvar
     * @return int Quantidade de arquivos baixados
     */
    private function downloadLogsFromPrefix(string $prefix, string $localPath): int
    {
        $s3Path = $this->s3Path . '/' . $prefix;

        try {
            // Usar AWS SDK S3Client diretamente
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => config('insights.s3_logs.region', config('filesystems.disks.s3.region', 'us-east-1')),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);

            $result = $s3Client->listObjectsV2([
                'Bucket' => $this->s3Bucket,
                'Prefix' => $s3Path,
            ]);

            if (!isset($result['Contents'])) {
                return 0;
            }

            $count = 0;
            foreach ($result['Contents'] as $object) {
                $key = $object['Key'];
                
                if (str_ends_with($key, '.log.gz')) {
                    $filename = basename($key);
                    $localFile = $localPath . '/' . $filename;

                    // Não baixar se já existe
                    if (File::exists($localFile)) {
                        continue;
                    }

                    // Baixar arquivo
                    $result = $s3Client->getObject([
                        'Bucket' => $this->s3Bucket,
                        'Key' => $key,
                    ]);
                    
                    File::put($localFile, $result['Body']->getContents());
                    $count++;
                }
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
     * @param  string  $dirPath  Caminho do diretório
     * @return int Quantidade de arquivos extraídos
     */
    private function extractGzFiles(string $dirPath): int
    {
        $count = 0;

        $gzFiles = glob($dirPath . '/*.gz');
        foreach ($gzFiles as $gzFile) {
            try {
                $outputFile = substr($gzFile, 0, -3); // Remove .gz

                // Executar gunzip
                exec("gunzip -f " . escapeshellarg($gzFile), $output, $returnCode);

                if ($returnCode === 0) {
                    $count++;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to extract {$gzFile}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Lista logs disponíveis para um incidente
     *
     * @param  string  $incidentId
     * @return array Array com caminhos dos arquivos .log
     */
    public function listLogsForIncident(string $incidentId): array
    {
        $incidentPath = $this->localBasePath . '/' . $incidentId;

        if (!File::isDirectory($incidentPath)) {
            return [];
        }

        return glob($incidentPath . '/*.log');
    }
}
