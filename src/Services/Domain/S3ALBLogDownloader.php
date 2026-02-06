<?php

namespace MatheusFS\Laravel\Insights\Services\Domain;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * S3 ALB Log Downloader
 * 
 * Implementação real que busca logs ALB do S3, analisa e armazena
 * 
 * Fluxo:
 * 1. Busca logs .gz do S3 para a data especificada
 * 2. Descompacta e parseia logs ALB
 * 3. Analisa com ALBLogAnalyzer (classificação API/UI/BOT/ASSETS)
 * 4. Salva resultado em JSON diário
 */
class S3ALBLogDownloader implements ALBLogDownloaderInterface
{
    private string $storage_path;
    private ALBLogAnalyzer $analyzer;
    private S3LogDownloaderService $s3_service;
    private LogParserService $log_parser;

    public function __construct(
        ALBLogAnalyzer $analyzer,
        S3LogDownloaderService $s3_service,
        LogParserService $log_parser,
        ?string $base_path = null
    ) {
        $this->analyzer = $analyzer;
        $this->s3_service = $s3_service;
        $this->log_parser = $log_parser;
        $this->storage_path = $base_path ?? config('insights.sre_metrics_path', storage_path('app/sre_metrics'));
    }

    /**
     * @inheritDoc
     */
    public function downloadForDate(Carbon $date, array $options = []): array
    {
        // Garantir que diretório mensal existe
        $month_dir = $this->getMonthDirectory($date);
        File::ensureDirectoryExists($month_dir, 0755, true);

        $day_file = $month_dir . '/' . $date->format('Y-m-d') . '.json';

        // Se já foi baixado e opção 'force' não está ativa, retornar cached
        if (File::exists($day_file) && !($options['force'] ?? false)) {
            return json_decode(File::get($day_file), true);
        }

        try {
            // Baixar logs do S3 para este dia
            $raw_logs = $this->fetchLogsFromS3($date);

            Log::info("About to analyze {$date->format('Y-m-d')}", [
                'raw_logs_count' => count($raw_logs),
                'is_array' => is_array($raw_logs),
                'first_entry_sample' => $raw_logs[0] ?? null,
            ]);

            // Analisar e classificar por tipo de serviço
            $analyzed = $this->analyzer->analyze($raw_logs, $date);

            // Armazenar para cache
            File::put($day_file, json_encode($analyzed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            Log::info("Logs ALB baixados e analisados para {$date->format('Y-m-d')}", [
                'total_requests' => array_sum(array_column($analyzed['by_request_type'], 'total_requests')),
            ]);

            return $analyzed;

        } catch (\Exception $e) {
            Log::error("Erro ao baixar logs ALB do S3 para {$date->format('Y-m-d')}: {$e->getMessage()}");

            // Retornar estrutura vazia se falhar
            $empty = $this->getEmptyLogStructure($date);
            File::put($day_file, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $empty;
        }
    }

    /**
     * @inheritDoc
     */
    public function downloadForMonth(string $month, array $options = []): array
    {
        // Formato esperado: 'Y-m' (ex: '2026-02')
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $aggregate = [
            'by_request_type' => [
                'API' => ['total_requests' => 0, 'errors_5xx' => 0],
                'UI' => ['total_requests' => 0, 'errors_5xx' => 0],
                'BOT' => ['total_requests' => 0, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 0, 'errors_5xx' => 0],
            ],
            'period' => [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        // Iterar sobre cada dia do mês
        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $day_logs = $this->downloadForDate($date, $options);

            // Agregar
            foreach (['API', 'UI', 'BOT', 'ASSETS'] as $service) {
                if (isset($day_logs['by_request_type'][$service])) {
                    $aggregate['by_request_type'][$service]['total_requests'] += 
                        $day_logs['by_request_type'][$service]['total_requests'] ?? 0;
                    
                    $aggregate['by_request_type'][$service]['errors_5xx'] += 
                        $day_logs['by_request_type'][$service]['errors_5xx'] ?? 0;
                }
            }
        }

        // Armazenar agregado mensal
        $month_dir = $this->getMonthDirectory($start);
        $aggregate_file = $month_dir . '/monthly_aggregate.json';
        File::put($aggregate_file, json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Log::info("Logs ALB agregados para o mês {$month}", [
            'total_requests' => array_sum(array_column($aggregate['by_request_type'], 'total_requests')),
        ]);

        return $aggregate;
    }

    /**
     * @inheritDoc
     */
    public function getStoragePath(): string
    {
        return $this->storage_path;
    }

    /**
     * @inheritDoc
     */
    public function hasDataForDate(Carbon $date): bool
    {
        $day_file = $this->getMonthDirectory($date) . '/' . $date->format('Y-m-d') . '.json';
        return File::exists($day_file);
    }

    /**
     * Busca logs do S3 para uma data específica
     * 
     * IMPORTANTE: Usa startOfDay para ambas as datas para evitar extender para próximo dia
     * 
     * @param Carbon $date
     * @return array Array de entradas de log parseadas
     */
    private function fetchLogsFromS3(Carbon $date): array
    {
        // Simular incidente para o dia (usa período de 24h)
        $incident_id = 'SRE-' . $date->format('Y-m-d');
        
        // Usar startOfDay para ambos - evita problemas com timezone ao converter para UTC
        $started_at = $date->copy()->startOfDay();
        $restored_at = $date->copy()->startOfDay()->addDay();

        // Usar S3LogDownloaderService para baixar logs
        // useMargins=false porque queremos período exato do dia (sem 1h antes/depois)
        $result = $this->s3_service->downloadLogsForIncident(
            $incident_id,
            $started_at,
            $restored_at,
            false // Sem margens - período exato
        );

        // Listar arquivos .log baixados
        $log_files = $this->s3_service->listLogsForIncident($incident_id);
        
        Log::info("Found {$result['downloaded_count']} files in S3 for {$date->format('Y-m-d')}", [
            'extracted_count' => $result['extracted_count'],
            'log_files_to_parse' => count($log_files),
        ]);

        // Parsear todos os logs usando LogParserService
        $parsed_logs = [];
        $total_files = count($log_files);
        
        foreach ($log_files as $index => $log_file) {
            $current_index = $index + 1;
            $filename = basename($log_file);
            Log::info("Parsing file [{$current_index}/{$total_files}]: {$filename}");
            
            $entries = $this->log_parser->parseLogFile($log_file);
            $parsed_logs = array_merge($parsed_logs, $entries);
        }

        Log::info("Fetched logs from S3 for {$date->format('Y-m-d')}", [
            'log_files_count' => count($log_files),
            'parsed_entries_count' => count($parsed_logs),
        ]);

        // Limpar arquivos temporários (opcional)
        // File::deleteDirectory($result['local_path']);

        return $parsed_logs;
    }

    /**
     * Retorna estrutura vazia de logs
     */
    private function getEmptyLogStructure(Carbon $date): array
    {
        return [
            'by_request_type' => [
                'API' => ['total_requests' => 0, 'errors_5xx' => 0],
                'UI' => ['total_requests' => 0, 'errors_5xx' => 0],
                'BOT' => ['total_requests' => 0, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 0, 'errors_5xx' => 0],
            ],
            'period' => [
                'start' => $date->startOfDay()->toIso8601String(),
                'end' => $date->endOfDay()->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Obtém diretório do mês
     */
    private function getMonthDirectory(Carbon $date): string
    {
        return $this->storage_path . '/' . $date->format('Y-m');
    }
}
