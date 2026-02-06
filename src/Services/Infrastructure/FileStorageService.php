<?php

namespace MatheusFS\Laravel\Insights\Services\Infrastructure;

use Illuminate\Support\Facades\File;

/**
 * FileStorageService - Leitura/escrita de arquivos de incidente
 *
 * Responsabilidade: I/O de arquivos relacionados a incidentes
 * Infrastructure layer
 */
class FileStorageService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = config('insights.incident_correlation.storage_path', storage_path('app/incidents'));
    }

    /**
     * Lê linhas de arquivo de log
     *
     * @param  string  $incidentId  ID do incidente
     * @param  string  $filename  Nome do arquivo (ex: 'access_log_parsed.csv')
     * @return array Linhas do arquivo
     */
    public function readLogFile(string $incidentId, string $filename): array
    {
        $filepath = $this->getIncidentPath($incidentId).'/'.$filename;

        if (! File::exists($filepath)) {
            throw new \RuntimeException("Log file not found: {$filename}");
        }

        return file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Lê todos os arquivos de log bruto de uma pasta
     *
     * @param  string  $subfolder  Subpasta (ex: '.raw_logs')
     * @return array Array de [filename => lines]
     */
    public function readRawLogs(string $subfolder = '.raw_logs'): array
    {
        $logDir = $this->getRawLogsDirectory($subfolder);

        if (! File::isDirectory($logDir)) {
            throw new \RuntimeException("Raw logs directory not found: {$logDir}");
        }

        $logFiles = File::files($logDir);
        $logs = [];

        foreach ($logFiles as $file) {
            $extension = $file->getExtension();

            if ($extension === 'log') {
                $logs[$file->getFilename()] = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                continue;
            }

            if ($extension === 'gz') {
                $logs[$file->getFilename()] = $this->readGzipLines($file->getPathname());
            }
        }

        return $logs;
    }

    /**
     * Lê linhas de arquivo .gz
     */
    private function readGzipLines(string $path): array
    {
        $lines = [];
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            return $lines;
        }

        while (! gzeof($handle)) {
            $line = gzgets($handle);
            if ($line === false) {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $lines[] = $trimmed;
        }

        gzclose($handle);

        return $lines;
    }

    /**
     * Retorna o diretório compartilhado de logs brutos
     */
    public function getRawLogsDirectory(string $subfolder = '.raw_logs'): string
    {
        return $this->basePath.'/'.$subfolder;
    }

    /**
     * Garante que o diretório compartilhado de logs existe
     */
    public function ensureRawLogsDirectory(string $subfolder = '.raw_logs'): void
    {
        $path = $this->getRawLogsDirectory($subfolder);

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * Salva dados processados em JSON
     *
     * @param  string  $incidentId  ID do incidente
     * @param  string  $filename  Nome do arquivo (ex: 'analyzed_traffic.json')
     * @param  array  $data  Dados para salvar
     */
    public function saveJsonData(string $incidentId, string $filename, array $data): void
    {
        $this->ensureIncidentDirectory($incidentId);

        $filepath = $this->getIncidentPath($incidentId).'/'.$filename;

        File::put($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Lê JSON de incidente
     *
     * @param  string  $incidentId  ID do incidente
     * @param  string  $filename  Nome do arquivo JSON
     * @return array Dados parseados
     */
    public function readJsonData(string $incidentId, string $filename): array
    {
        $filepath = $this->getIncidentPath($incidentId).'/'.$filename;

        if (! File::exists($filepath)) {
            throw new \RuntimeException("JSON file not found: {$filename}");
        }

        return json_decode(File::get($filepath), true);
    }

    /**
     * Salva CSV com resultados
     *
     * @param  string  $incidentId  ID do incidente
     * @param  string  $filename  Nome do arquivo CSV
     * @param  array  $rows  Array de arrays (rows)
     * @param  array|null  $headers  Headers opcionais
     */
    public function saveCsvData(string $incidentId, string $filename, array $rows, ?array $headers = null): void
    {
        $this->ensureIncidentDirectory($incidentId);

        $filepath = $this->getIncidentPath($incidentId).'/'.$filename;

        $fp = fopen($filepath, 'w');

        if ($headers) {
            fputcsv($fp, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    /**
     * Lista todos os incidentes disponíveis
     *
     * @return array Array de IDs de incidentes
     */
    public function listIncidents(): array
    {
        if (! File::isDirectory($this->basePath)) {
            return [];
        }

        $directories = File::directories($this->basePath);

        return array_map(fn ($dir) => basename($dir), $directories);
    }

    /**
     * Verifica se incidente existe
     *
     * @param  string  $incidentId  ID do incidente
     */
    public function incidentExists(string $incidentId): bool
    {
        return File::isDirectory($this->getIncidentPath($incidentId));
    }

    /**
     * Cria diretório de incidente se não existir
     *
     * @param  string  $incidentId  ID do incidente
     */
    public function ensureIncidentDirectory(string $incidentId): void
    {
        $path = $this->getIncidentPath($incidentId);

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * Retorna path completo do incidente
     */
    private function getIncidentPath(string $incidentId): string
    {
        return $this->basePath.'/'.$incidentId;
    }
}
