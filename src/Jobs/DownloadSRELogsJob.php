<?php

namespace MatheusFS\Laravel\Insights\Jobs;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MatheusFS\Laravel\Insights\ValueObjects\SREMetricsAggregate;

/**
 * Job para baixar logs ALB em background
 * 
 * Disparado automaticamente quando endpoint /sre-metrics detecta ausência de dados
 */
class DownloadSRELogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $month;
    public bool $force;

    /**
     * Timeout de 10 minutos (download pode ser demorado)
     */
    public int $timeout = 600;

    /**
     * Número de tentativas
     */
    public int $tries = 2;

    public function __construct(string $month, bool $force = false)
    {
        $this->month = $month;
        $this->force = $force;
    }

    public function handle(ALBLogDownloaderInterface $downloader): void
    {
        Log::info("DownloadSRELogsJob: Iniciando download de logs para {$this->month}", [
            'force' => $this->force,
        ]);

        try {
            $options = ['force' => $this->force];
            $aggregate = $downloader->downloadForMonth($this->month, $options);
            $metrics = SREMetricsAggregate::fromArray($aggregate);
            $total_requests = $metrics->totalRequests();

            Log::info("DownloadSRELogsJob: Download concluído para {$this->month}", [
                'total_requests' => $total_requests,
                'api_requests' => $metrics->api->total_requests,
                'ui_requests' => $metrics->ui->total_requests,
            ]);
        } catch (\Exception $e) {
            Log::error("DownloadSRELogsJob: Erro ao baixar logs para {$this->month}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lançar exceção para que o job seja marcado como falho e retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("DownloadSRELogsJob: Falha definitiva ao baixar logs para {$this->month}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
