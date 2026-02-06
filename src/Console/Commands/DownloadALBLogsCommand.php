<?php

namespace MatheusFS\Laravel\Insights\Console\Commands;

use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * Comando para Baixar Logs ALB Diariamente
 * 
 * Uso:
 * - Download de hoje: php artisan alb:download-logs
 * - Download de data específica: php artisan alb:download-logs --date=2026-02-05
 * - Download de mês inteiro: php artisan alb:download-logs --month=2026-02
 * - Force (ignorar cache): php artisan alb:download-logs --force
 * 
 * Agendamento (Kernel.php do app consumer):
 * $schedule->command('alb:download-logs')
 *          ->dailyAt('00:30')  // Rodar todo dia às 00:30 (baixa dados de ontem)
 *          ->withoutOverlapping();
 */
class DownloadALBLogsCommand extends Command
{
    protected $signature = 'alb:download-logs {--date=} {--month=} {--force}';

    protected $description = 'Download ALB logs for SRE metrics calculation';

    private ALBLogDownloaderInterface $downloader;

    public function __construct(ALBLogDownloaderInterface $downloader)
    {
        parent::__construct();
        $this->downloader = $downloader;
    }

    public function handle(): int
    {
        try {
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

        $total = $logs['by_request_type']['API']['total_requests'] +
                 $logs['by_request_type']['UI']['total_requests'] +
                 $logs['by_request_type']['BOT']['total_requests'] +
                 $logs['by_request_type']['ASSETS']['total_requests'];

        $this->info("✅ Logs baixados com sucesso!");
        $this->line("   Data: {$date->format('Y-m-d')}");
        $this->line("   Total de requisições: " . number_format($total));
        $this->line("     - API: {$logs['by_request_type']['API']['total_requests']} (5xx: {$logs['by_request_type']['API']['errors_5xx']})");
        $this->line("     - UI: {$logs['by_request_type']['UI']['total_requests']} (5xx: {$logs['by_request_type']['UI']['errors_5xx']})");
        $this->line("     - BOT: {$logs['by_request_type']['BOT']['total_requests']}");
        $this->line("     - ASSETS: {$logs['by_request_type']['ASSETS']['total_requests']}");

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

        $total = $aggregate['by_request_type']['API']['total_requests'] +
                 $aggregate['by_request_type']['UI']['total_requests'] +
                 $aggregate['by_request_type']['BOT']['total_requests'] +
                 $aggregate['by_request_type']['ASSETS']['total_requests'];

        $this->info("✅ Logs mensais agregados com sucesso!");
        $this->line("   Período: {$month}");
        $this->line("   Total de requisições: " . number_format($total));
        $this->line("     - API: {$aggregate['by_request_type']['API']['total_requests']} (5xx: {$aggregate['by_request_type']['API']['errors_5xx']})");
        $this->line("     - UI: {$aggregate['by_request_type']['UI']['total_requests']} (5xx: {$aggregate['by_request_type']['UI']['errors_5xx']})");
        $this->line("     - BOT: {$aggregate['by_request_type']['BOT']['total_requests']}");
        $this->line("     - ASSETS: {$aggregate['by_request_type']['ASSETS']['total_requests']}");

        return Command::SUCCESS;
    }
}
