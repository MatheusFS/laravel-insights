<?php

namespace MatheusFS\Laravel\Insights\Services\Domain;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Downloader de Logs ALB
 * 
 * Responsabilidades:
 * 1. Baixar logs do ALB (CloudWatch em produção, local em dev)
 * 2. Analisar e agregar por tipo de serviço
 * 3. Armazenar em storage estruturado (sre_metrics/YYYY-MM/YYYY-MM-DD.json)
 * 4. Fornecer interface para leitura histórica
 */
class ALBLogDownloader implements ALBLogDownloaderInterface
{
    /**
     * Caminho base de armazenamento
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
        $this->storage_path = $base_path ?? storage_path('app/sre_metrics');
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
