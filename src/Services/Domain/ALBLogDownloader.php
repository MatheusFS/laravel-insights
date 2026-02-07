<?php

namespace MatheusFS\Laravel\Insights\Services\Domain;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Downloader de Logs ALB (Implementação Mock/Local)
 * 
 * IMPORTANTE: Esta é uma implementação mock para desenvolvimento e testes.
 * Em produção, use S3ALBLogDownloader que baixa logs reais do S3 para o diretório unificado.
 * 
 * Responsabilidades:
 * 1. Simular logs ALB em ambiente de desenvolvimento
 * 2. Analisar e agregar por tipo de serviço
 * 3. Armazenar JSON calculados em sre_metrics/YYYY-MM/YYYY-MM-DD.json
 * 4. Fornecer interface para leitura histórica
 * 
 * NOTA: Esta implementação NÃO baixa logs para access_logs_path pois usa dados mock locais.
 */
class ALBLogDownloader implements ALBLogDownloaderInterface
{
    /**
     * Implementação LOCAL/MOCK - para desenvolvimento ou testes
     * 
     * Retorna dados mockados ou do armazenamento local.
     * NÃO faz download de S3/CloudWatch.
     * 
     * Use este método para validar que a implementação correta está sendo usada.
     */
    public function getLogSource(): string
    {
        return 'local';
    }

    /**
     * Caminho base para armazenamento de JSON calculados (NÃO logs brutos)
     */
    private string $storage_path;

    /**
     * Analisador de logs (extrai tipos de requisição e erros)
     */
    private ALBLogAnalyzer $analyzer;

    public function __construct(
        ALBLogAnalyzer $analyzer,
        ?string $base_path = null
    ) {
        $this->analyzer = $analyzer;
        $this->storage_path = $base_path ?: (config('insights.sre_metrics_path') ?: storage_path('insights/reliability/sre-metrics'));
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

        // Simular ou buscar logs ALB reais
        // Em produção: buscar de CloudWatch
        // Em desenvolvimento: usar mock ou arquivo local
        if (config('insights.alb_source') === 'cloudwatch' && !app()->environment('testing')) {
            $logs = $this->fetchFromCloudWatch($date, $options);
        } else {
            // Mock: Se existir arquivo mock local, usar
            $mock_file = $this->getMockFilePath($date);
            if (File::exists($mock_file)) {
                $logs = json_decode(File::get($mock_file), true);
            } else {
                // Retornar dados vazios (será agregado com outros dias)
                $logs = $this->getEmptyLogStructure();
            }
        }

        // Analisar e agregar por tipo de serviço
        $analyzed = $this->analyzer->analyze($logs, $date);

        // Armazenar para cache
        File::put($day_file, json_encode($analyzed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $analyzed;
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
     * Busca logs do CloudWatch (implementação real para produção)
     * 
     * @param Carbon $date
     * @param array $options
     * @return array
     */
    private function fetchFromCloudWatch(Carbon $date, array $options = []): array
    {
        // TODO: Implementar integração com CloudWatch
        // Use AWS SDK v3 para buscar logs
        // Filtrar por padrão de ALB log (status code, request type, etc)
        
        return $this->getEmptyLogStructure();
    }

    /**
     * Retorna caminho do arquivo mock para desenvolvimento
     * 
     * @param Carbon $date
     * @return string
     */
    private function getMockFilePath(Carbon $date): string
    {
        return storage_path('app/alb_logs_mock/' . $date->format('Y-m-d') . '.json');
    }

    /**
     * Retorna caminho do diretório mensal
     * 
     * @param Carbon $date
     * @return string
     */
    private function getMonthDirectory(Carbon $date): string
    {
        return $this->storage_path . '/' . $date->format('Y-m');
    }

    /**
     * Estrutura vazia de logs (quando nenhum dado disponível)
     * 
     * @return array
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
     * @inheritDoc
     */
    private function getEmptyLogStructure(): array
    {
        return [
            'by_request_type' => [
                'API' => ['total_requests' => 0, 'errors_5xx' => 0],
                'UI' => ['total_requests' => 0, 'errors_5xx' => 0],
                'BOT' => ['total_requests' => 0, 'errors_5xx' => 0],
                'ASSETS' => ['total_requests' => 0, 'errors_5xx' => 0],
            ],
        ];
    }
}
