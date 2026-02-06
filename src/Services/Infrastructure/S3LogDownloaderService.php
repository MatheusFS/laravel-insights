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
        $this->s3Bucket = config('insights.incident_correlation.s3_bucket', 'refresher-logs');
        $this->s3Path = config('insights.incident_correlation.s3_path', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1');
        $this->localBasePath = config('insights.incident_correlation.storage_path', storage_path('app/incidents')) . '/.raw_logs';

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
        Carbon $restoredAt
    ): array {
        // Criar pasta específica para o incidente
        $incidentPath = $this->localBasePath . '/' . $incidentId;
        if (!File::isDirectory($incidentPath)) {
            File::makeDirectory($incidentPath, 0755, true);
        }

        // Determinar período de logs a buscar (com margem de 1 hora antes/depois)
        $searchStartDate = $startedAt->clone()->subHour();
        $searchEndDate = $restoredAt->clone()->addHour();

        // Gerar lista de prefixos S3 para as datas (YYYY/MM/DD/HH)
        $s3Prefixes = $this->generateS3Prefixes($searchStartDate, $searchEndDate);

        $downloadedCount = 0;

        // Baixar logs de cada prefix
        foreach ($s3Prefixes as $prefix) {
            $logsInPrefix = $this->downloadLogsFromPrefix($prefix, $incidentPath);
            $downloadedCount += $logsInPrefix;
        }

        // Descompactar todos os .gz
        $extractedCount = $this->extractGzFiles($incidentPath);

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
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     * @return array Lista de prefixos (YYYY/MM/DD/HH)
     */
    private function generateS3Prefixes(Carbon $startDate, Carbon $endDate): array
    {
        $prefixes = [];
        $current = $startDate->clone()->startOfHour();

        while ($current->lte($endDate)) {
            $prefix = sprintf(
                '%d/%02d/%02d/',
                $current->year,
                $current->month,
                $current->day
            );

            if (!in_array($prefix, $prefixes)) {
                $prefixes[] = $prefix;
            }

            $current->addHour();
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
            $s3 = Storage::disk('s3');

            // Listar arquivos no prefixo
            $files = $s3->listContents($s3Path);

            $count = 0;
            foreach ($files as $file) {
                if ($file['type'] === 'file' && str_ends_with($file['path'], '.log.gz')) {
                    $filename = basename($file['path']);
                    $localFile = $localPath . '/' . $filename;

                    // Não baixar se já existe
                    if (File::exists($localFile)) {
                        continue;
                    }

                    // Baixar arquivo
                    $content = $s3->get($file['path']);
                    File::put($localFile, $content);
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
