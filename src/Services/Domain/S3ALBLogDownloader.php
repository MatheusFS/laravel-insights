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
    /**
     * Implementação S3 - para produção
     * 
     * Download de logs do S3, parsing e análise.
     * Este é o downloader padrão em produção (ALB_LOG_SOURCE=s3).
     * 
     * Use este método para validar que a implementação correta está sendo usada.
     */
    public function getLogSource(): string
    {
        return 's3';
    }

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
            $cached = json_decode(File::get($day_file), true);

            if (is_array($cached) && isset($cached['by_request_type'])) {
                $totalRequests = array_sum(array_column($cached['by_request_type'], 'total_requests'));
                $totalErrors = array_sum(array_column($cached['by_request_type'], 'errors_5xx'));

                if ($totalRequests === 0 && $totalErrors === 0) {
                    Log::warning("Empty SRE day cache detected, reprocessing {$date->format('Y-m-d')}", [
                        'day_file' => $day_file,
                    ]);
                } else {
                    return $cached;
                }
            } else {
                return $cached;
            }
        }

        try {
            // Baixar logs do S3 para este dia
            // Passar $options['force'] para que S3LogDownloaderService
            // saiba se deve re-extrair .log mesmo se já existe
            $raw_logs = $this->fetchLogsFromS3($date, $options);

            Log::info("Raw logs fetched from S3", [
                'date' => $date->format('Y-m-d'),
                'raw_logs_count' => count($raw_logs),
                'is_array' => is_array($raw_logs),
                'first_entry_sample' => isset($raw_logs[0]) ? [
                    'path' => $raw_logs[0]['path'] ?? null,
                    'status_code' => $raw_logs[0]['status_code'] ?? null,
                    'timestamp' => $raw_logs[0]['timestamp'] ?? null,
                ] : null,
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
            Log::info("Processing day for SRE metrics", [
                'date' => $date->format('Y-m-d'),
                'month' => $month,
            ]);
            
            $day_logs = $this->downloadForDate($date, $options);

            $day_total = array_sum(array_column($day_logs['by_request_type'], 'total_requests'));
            Log::info("Day logs aggregated", [
                'date' => $date->format('Y-m-d'),
                'total_requests' => $day_total,
                'api' => $day_logs['by_request_type']['API']['total_requests'] ?? 0,
                'ui' => $day_logs['by_request_type']['UI']['total_requests'] ?? 0,
            ]);

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

        $total_requests = array_sum(array_column($aggregate['by_request_type'], 'total_requests'));
        Log::info("Monthly aggregate saved", [
            'month' => $month,
            'file' => $aggregate_file,
            'total_requests' => $total_requests,
            'api_requests' => $aggregate['by_request_type']['API']['total_requests'] ?? 0,
            'ui_requests' => $aggregate['by_request_type']['UI']['total_requests'] ?? 0,
            'bot_requests' => $aggregate['by_request_type']['BOT']['total_requests'] ?? 0,
            'assets_requests' => $aggregate['by_request_type']['ASSETS']['total_requests'] ?? 0,
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
     * Usa start/end of day UTC para buscar logs de 24h completas
     * 
     * @param Carbon $date
     * @param array $options
     * @return array Array de entradas de log parseadas
     */
    private function fetchLogsFromS3(Carbon $date, array $options = []): array
    {
        // Período de 24h completas (00:00:00 a 23:59:59 UTC)
        $start_utc = $date->clone()->setTimezone('UTC')->startOfDay();
        $end_utc = $date->clone()->setTimezone('UTC')->endOfDay();
        
        $force = $options['force'] ?? false;
        
        Log::info("Fetching S3 logs for {$date->format('Y-m-d')}", [
            'start_utc' => $start_utc->toIso8601String(),
            'end_utc' => $end_utc->toIso8601String(),
            'force' => $force,
        ]);

        try {
            // Baixar e extrair logs diretamente do S3 usando período
            $result = $this->s3_service->downloadLogsForPeriod(
                $start_utc,
                $end_utc,
                forceExtraction: $force
            );

            // Listar arquivos .log extraídos
            $all_files = $this->s3_service->listAllFiles();
            $log_files = $all_files['extracted'] ?? [];
            
            \Log::info("S3 download complete", [
                'date' => $date->format('Y-m-d'),
                'downloaded_count' => $result['downloaded_count'],
                'extracted_count' => $result['extracted_count'],
                'log_files_count' => count($log_files),
                'sample_files' => array_slice(array_map('basename', $log_files), 0, 3),
            ]);

            // ===== PARSING CACHE LAYER: Apenas processar arquivos NÃO processados =====
            // Usar .parsed marker file para rastrear quais foram processados
            $parsed_logs = [];
            $filesToParse = $this->getUnparsedLogFiles($log_files, $force);
            $skipped_count = count($log_files) - count($filesToParse);
            
            if ($skipped_count > 0) {
                \Log::info("Parsing cache hit: skipping {$skipped_count} already processed files", [
                    'date' => $date->format('Y-m-d'),
                ]);
            }

            // Parsear apenas os logs NÃO processados
            foreach ($filesToParse as $index => $log_file) {
                $filename = basename($log_file);
                
                \Log::debug("Processing log file [{" . ($index + 1) . "}/" . count($filesToParse) . "]", [
                    'filename' => $filename,
                    'file_path' => $log_file,
                ]);
                
                $entries = $this->log_parser->parseLogFile($log_file);
                $parsed_logs = array_merge($parsed_logs, $entries);
                
                // Marcar como processado
                $this->markFileAsParsed($log_file);
                
                if ($index < 3 || count($entries) > 0) {
                    \Log::info("Parsed file [" . ($index + 1) . "/" . count($filesToParse) . "]", [
                        'filename' => $filename,
                        'entries_count' => count($entries),
                    ]);
                }
            }

            \Log::info("Parsing complete", [
                'date' => $date->format('Y-m-d'),
                'total_files_available' => count($log_files),
                'files_parsed_this_run' => count($filesToParse),
                'files_skipped_cached' => $skipped_count,
                'total_entries_parsed' => count($parsed_logs),
                'sample_entry' => $parsed_logs[0] ?? null,
            ]);

            return $parsed_logs;

        } catch (\Exception $e) {
            \Log::error("S3 fetch error for {$date->format('Y-m-d')}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [];
        }
    }

    /**
     * Obtém lista de arquivos .log não processados ainda
     * 
     * **Parsing Cache Layer:** Se arquivo .log.parsed existe, significa que
     * o arquivo já foi parseado e análise foi feita. Pula processamento.
     * 
     * Com --force: Ignora cache e reprocessa todos
     * Sem --force: Retorna apenas novos + modifica que mudaram desde última parse
     * 
     * @param  array   $all_log_files  Lista de caminhos de .log
     * @param  bool    $forceReparse   Se true, ignora cache e reprocessa tudo
     * @return array Lista filtrada apenas arquivos a processar
     */
    private function getUnparsedLogFiles(array $all_log_files, bool $forceReparse = false): array
    {
        if ($forceReparse) {
            // Force: reprocessar TUDO
            return $all_log_files;
        }

        // Sem force: retornar apenas não-parseados
        $unparsed = [];
        foreach ($all_log_files as $log_file) {
            $parsed_marker = $log_file . '.parsed';
            
            // Se marker não existe OU arquivo modificado depois do marker: processar
            if (!File::exists($parsed_marker)) {
                $unparsed[] = $log_file;
                continue;
            }

            // Verificar se arquivo foi modificado DEPOIS do marker
            $fileModTime = filemtime($log_file);
            $markerModTime = filemtime($parsed_marker);
            
            if ($fileModTime > $markerModTime) {
                $unparsed[] = $log_file;
            }
        }

        return $unparsed;
    }

    /**
     * Marca um arquivo como parseado criando um marker .parsed
     * 
     * Usado para rastrear quais arquivos já foram processados,
     * evitando reprocessamento em próximas execuções.
     * 
     * @param  string  $log_file  Caminho completo do arquivo .log
     * @return bool True se marker criado
     */
    private function markFileAsParsed(string $log_file): bool
    {
        try {
            $parsed_marker = $log_file . '.parsed';
            File::put($parsed_marker, json_encode([
                'parsed_at' => now()->toIso8601String(),
                'original_file' => $log_file,
                'file_size' => filesize($log_file),
            ]));
            return true;
        } catch (\Exception $e) {
            \Log::warning("Failed to create parsing marker for {$log_file}: {$e->getMessage()}");
            return false;
        }
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
     * @inheritDoc
     */
    public function downloadLogsForPeriod(Carbon $start, Carbon $end, bool $force = false): array
    {
        // Normalizar datas para UTC startOfDay
        $current = $start->clone()->setTimezone('UTC')->startOfDay();
        $end_date = $end->clone()->setTimezone('UTC')->startOfDay()->addDay();

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
            'days_included' => 0,
        ];

        $options = ['force' => $force];
        
        // Iterar por cada dia do período
        while ($current->lt($end_date)) {
            $day_data = $this->downloadForDate($current, $options);
            
            // Agregar resultados
            foreach (['API', 'UI', 'BOT', 'ASSETS'] as $type) {
                $aggregate['by_request_type'][$type]['total_requests'] += $day_data['by_request_type'][$type]['total_requests'] ?? 0;
                $aggregate['by_request_type'][$type]['errors_5xx'] += $day_data['by_request_type'][$type]['errors_5xx'] ?? 0;
            }
            
            $aggregate['days_included']++;
            $current->addDay();
        }

        return $aggregate;
    }

    /**
     * Obtém diretório do mês
     */
    private function getMonthDirectory(Carbon $date): string
    {
        return $this->storage_path . '/' . $date->format('Y-m');
    }

    // ========== PUBLIC TESTING METHODS ==========
    // Métodos públicos para facilitar testes da cache logic

    /**
     * @testable - Método exposto para testes
     */
    public function testGetUnparsedLogFiles(array $all_log_files, bool $forceReparse = false): array
    {
        return $this->getUnparsedLogFiles($all_log_files, $forceReparse);
    }

    /**
     * @testable - Método exposto para testes
     */
    public function testMarkFileAsParsed(string $log_file): bool
    {
        return $this->markFileAsParsed($log_file);
    }
}
