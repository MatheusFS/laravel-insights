<?php

namespace MatheusFS\Laravel\Insights\Console\Commands;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Illuminate\Console\Command;
use Carbon\Carbon;
use MatheusFS\Laravel\Insights\ValueObjects\SREMetricsAggregate;

/**
 * Comando para Baixar Logs ALB
 * 
 * Uso:
 * 1. Download de hoje: php artisan alb:download-logs
 * 2. Download de data específica: php artisan alb:download-logs --date=2026-02-05
 * 3. Download de mês inteiro: php artisan alb:download-logs --month=2026-02
 * 4. Download de período customizado: php artisan alb:download-logs --start=2026-02-01T00:00:00Z --end=2026-02-05T23:59:59Z
 * 5. Force (ignorar cache): php artisan alb:download-logs --force
 * 
 * Agendamento (Kernel.php do app consumer):
 * $schedule->command('alb:download-logs')
 *          ->dailyAt('00:30')  // Rodar todo dia às 00:30 (baixa dados de ontem)
 *          ->withoutOverlapping();
 * 
 * IMPORTANTE: Todos os logs são baixados para {access_logs_path} compartilhado.
 * Logs com intersecção de períodos são reutilizados (não re-baixam nem re-extraem).
 */
class DownloadALBLogsCommand extends Command
{
    protected $signature = 'alb:download-logs {--date=} {--month=} {--start=} {--end=} {--force}';

    protected $description = 'Download ALB logs for SRE metrics calculation (shared unified directory, smart caching)';

    private ALBLogDownloaderInterface $downloader;

    public function __construct(ALBLogDownloaderInterface $downloader)
    {
        parent::__construct();
        $this->downloader = $downloader;
    }

    public function handle(): int
    {
        try {
            // Prioridade: --start/--end > --month > --date > padrão (ontem)
            if ($this->option('start') || $this->option('end')) {
                return $this->downloadPeriod();
            }

            if ($this->option('month')) {
                return $this->downloadMonth();
            }

            return $this->downloadDate();
        } catch (\Exception $e) {
            $this->error("Erro ao baixar logs: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Download de data específica ou ontem
     */
    private function downloadDate(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Baixando logs ALB para {$date->format('Y-m-d')}...");

        $options = [
            'force' => $this->option('force') ?? false,
        ];

        $logs = $this->downloader->downloadForDate($date, $options);
        $metrics = SREMetricsAggregate::fromArray($logs);
        $total = $metrics->totalRequests();

        $this->info("✅ Logs baixados com sucesso!");
        $this->line("   Data: {$date->format('Y-m-d')}");
        $this->line("   Total de requisições: " . number_format($total));
        $this->line("     - API: {$metrics->api->total_requests} (5xx: {$metrics->api->errors_5xx})");
        $this->line("     - UI: {$metrics->ui->total_requests} (5xx: {$metrics->ui->errors_5xx})");
        $this->line("     - BOT: {$metrics->bot->total_requests}");
        $this->line("     - ASSETS: {$metrics->assets->total_requests}");

        return Command::SUCCESS;
    }

    /**
     * Download de mês inteiro
     */
    private function downloadMonth(): int
    {
        $month = $this->option('month');

        // Validar formato
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Formato de mês inválido. Use: YYYY-MM (ex: 2026-02)");
            return Command::FAILURE;
        }

        $this->info("Baixando logs ALB para o mês de {$month}...");

        $options = [
            'force' => $this->option('force') ?? false,
        ];

        $aggregate = $this->downloader->downloadForMonth($month, $options);
        $metrics = SREMetricsAggregate::fromArray($aggregate);
        $total = $metrics->totalRequests();

        $this->info("✅ Logs mensais agregados com sucesso!");
        $this->line("   Período: {$month}");
        $this->line("   Total de requisições: " . number_format($total));
        $this->line("     - API: {$metrics->api->total_requests} (5xx: {$metrics->api->errors_5xx})");
        $this->line("     - UI: {$metrics->ui->total_requests} (5xx: {$metrics->ui->errors_5xx})");
        $this->line("     - BOT: {$metrics->bot->total_requests}");
        $this->line("     - ASSETS: {$metrics->assets->total_requests}");

        return Command::SUCCESS;
    }

    /**
     * Download de período customizado (--start e --end)
     * 
     * IMPORTANTE: Usa o diretório unificado de logs (access_logs_path).
     * Se há intersecção com períodos anteriores, reutiliza logs já baixados.
     */
    private function downloadPeriod(): int
    {
        $startStr = $this->option('start');
        $endStr = $this->option('end');

        if (!$startStr || !$endStr) {
            $this->error("--start e --end são obrigatórios. Ex: --start=2026-02-01T00:00:00Z --end=2026-02-05T23:59:59Z");
            return Command::FAILURE;
        }

        try {
            $start = Carbon::parse($startStr);
            $end = Carbon::parse($endStr);

            if ($start->gt($end)) {
                $this->error("--start não pode ser maior que --end");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Erro ao fazer parse das datas: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $this->info("Baixando logs ALB para período customizado...");
        $this->line("   Início: {$start->toIso8601String()}");
        $this->line("   Fim: {$end->toIso8601String()}");

        // Usar novo método downloadLogsForPeriod do downloader
        if (!method_exists($this->downloader, 'downloadLogsForPeriod')) {
            $this->error("ALBLogDownloader não suporta downloadLogsForPeriod. Atualize o pacote.");
            return Command::FAILURE;
        }

        $result = $this->downloader->downloadLogsForPeriod(
            $start,
            $end,
            $this->option('force') ?? false
        );

        $this->info("✅ Logs baixados com sucesso!");
        $this->line("   Período: {$start->format('Y-m-d H:i:s')} a {$end->format('Y-m-d H:i:s')}");
        $this->line("   Arquivos baixados: {$result['downloaded_count']}");
        $this->line("   Arquivos extraídos: {$result['extracted_count']}");
        $this->line("   Diretório: {$result['local_path']}");
        $this->line("");
        $this->comment("💡 Dica: Os logs foram salvos no diretório unificado. Você pode usar esse período em análises de incidente.");

        return Command::SUCCESS;
    }
}