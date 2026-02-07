<?php

namespace MatheusFS\Laravel\Insights\Services\Infrastructure;

use Illuminate\Support\Facades\File;

/**
 * FileStorageService - Leitura/escrita de arquivos de incidente
 *
 * Responsabilidade: I/O de arquivos relacionados a incidentes
 * Infrastructure layer
 * 
 * ARQUITETURA DE DIRETÓRIOS:
 * - access_logs_path: Diretório UNIFICADO para TODOS os logs .log (compartilhado)
 * - incidents_path: Diretório para JSON de análises por incidente (INC-2026-001/*.json)
 */
class FileStorageService
{
    protected string $basePath;

    public function __construct()
    {
        // Usar a nova configuração incidents_path ao invés de incident_correlation.storage_path
        $this->basePath = config('insights.incidents_path', storage_path('insights/reliability/incidents'));
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
     * @deprecated Este método é legado e não deve ser usado.
     *             Use config('insights.access_logs_path') para acessar logs unificados.
     *             Os logs agora são compartilhados entre incidentes no diretório unificado.
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
     * 
     * @deprecated Este método é legado e não deve ser usado.
     *             Use config('insights.access_logs_path') para o diretório unificado de logs.
     */
    public function getRawLogsDirectory(string $subfolder = '.raw_logs'): string
    {
        return $this->basePath.'/'.$subfolder;
    }
    /**
     * Garante que o diretório compartilhado de logs existe
     * @deprecated Este método é legado e não deve ser usado.
     *             O diretório unificado é criado automaticamente por S3LogDownloaderService.
     *
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
     * Salva no padrão de diretório: {incidents_path}/INC-ID/{filename}.json
     * Garante isolamento de JSONs por incidente.
     * 
     * Exemplo:
     * - Entrada: incidentId=INC-2026-001, filename=alb_logs_analysis
     * - Saída: {incidents_path}/INC-2026-001/alb_logs_analysis.json
     * 
     * @param  string  $incidentId  ID do incidente
     * @param  string  $filename  Nome do arquivo (ex: 'alb_logs_analysis')
     * @param  array  $data  Dados para salvar
     */
    public function saveJsonData(string $incidentId, string $filename, array $data): void
    {
        $incidentDir = $this->ensureIncidentDirectory($incidentId);

        // Adicionar .json se não tiver
        $filename = str_ends_with($filename, '.json') ? $filename : $filename . '.json';

        $filepath = $incidentDir . '/' . $filename;

        File::put($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        \Log::info("Saved JSON data", [
            'incident_id' => $incidentId,
            'file' => $filepath,
        ]);
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
        // Adicionar .json se não tiver
        $filename = str_ends_with($filename, '.json') ? $filename : $filename . '.json';

        $filepath = $this->getIncidentPath($incidentId) . '/' . $filename;

        if (!File::exists($filepath)) {
            throw new \RuntimeException("JSON file not found: {$filename} for incident {$incidentId}");
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
        $incidentDir = $this->ensureIncidentDirectory($incidentId);

        // Adicionar .csv se não tiver
        $filename = str_ends_with($filename, '.csv') ? $filename : $filename . '.csv';

        $filepath = $incidentDir . '/' . $filename;

        $fp = fopen($filepath, 'w');

        if ($headers) {
            fputcsv($fp, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        \Log::info("Saved CSV data", [
            'incident_id' => $incidentId,
            'file' => $filepath,
        ]);
    }

    /**
     * Lista todos os incidentes disponíveis
     *
     * Busca por diretórios com padrão INC-YYYY-NNN
     *
     * @return array Array de IDs de incidentes
     */
    public function listIncidents(): array
    {
        if (!File::isDirectory($this->basePath)) {
            return [];
        }

        $dirs = File::directories($this->basePath);
        $incidentIds = [];

        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            // Validar padrão INC-YYYY-NNN
            if (preg_match('/^INC-\d{4}-\d{3}$/i', $dirName)) {
                $incidentIds[] = $dirName;
            }
        }

        return $incidentIds;
    }

    /**
     * Verifica se incidente existe
     *
     * @param  string  $incidentId  ID do incidente
     */
    public function incidentExists(string $incidentId): bool
    {
        $incidentPath = $this->getIncidentPath($incidentId);
        return File::isDirectory($incidentPath);
    }

    /**
     * Cria/garante diretório de incidente
     */
    private function ensureIncidentDirectory(string $incidentId): string
    {
        $incidentDir = $this->getIncidentPath($incidentId);

        if (!File::isDirectory($incidentDir)) {
            File::makeDirectory($incidentDir, 0755, true);
        }

        return $incidentDir;
    }

    /**
     * Retorna caminho do incidente
     */
    private function getIncidentPath(string $incidentId): string
    {
        return $this->basePath . '/' . $incidentId;
    }

    /**
     * Cria diretório base se não existir
     */
    private function ensureBaseDirectory(): void
    {
        if (!File::isDirectory($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }
    }
}
