<?php

namespace MatheusFS\Laravel\Insights;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use MatheusFS\Laravel\Insights\Providers\BladeServiceProvider;
use MatheusFS\Laravel\Insights\Providers\EventServiceProvider;
use MatheusFS\Laravel\Insights\Providers\RouteServiceProvider;
use MatheusFS\Laravel\Insights\Services\IncidentCorrelationService;
use MatheusFS\Laravel\Insights\Services\Domain\AccessLog\LogParserService;
use MatheusFS\Laravel\Insights\Services\Domain\Traffic\TrafficAnalyzerService;
use MatheusFS\Laravel\Insights\Services\Domain\Incident\IncidentMetricsCalculator;
use MatheusFS\Laravel\Insights\Services\Domain\Security\WAFRuleGeneratorService;
use MatheusFS\Laravel\Insights\Services\Infrastructure\FileStorageService;
use MatheusFS\Laravel\Insights\Contracts\ALBLogDownloaderInterface;
use MatheusFS\Laravel\Insights\Services\Domain\ALBLogDownloader;
use MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader;
use MatheusFS\Laravel\Insights\Services\Domain\ALBLogAnalyzer;
use MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService;
use MatheusFS\Laravel\Insights\Console\Commands\DownloadALBLogsCommand;

class ServiceProvider extends BaseServiceProvider {

    public function register() {

        $this->mergeConfigFrom(__DIR__.'/../config/insights.php', 'insights');
        
        // Register sub-providers
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(BladeServiceProvider::class);

        // Register domain services as singletons (stateless)
        $this->app->singleton(LogParserService::class);
        $this->app->singleton(TrafficAnalyzerService::class);
        $this->app->singleton(IncidentMetricsCalculator::class);
        $this->app->singleton(WAFRuleGeneratorService::class);

        // Register infrastructure services
        $this->app->singleton(FileStorageService::class);

        // Register ALB Log services
        $this->app->singleton(ALBLogAnalyzer::class);
        $this->app->singleton(S3LogDownloaderService::class);
        $this->app->singleton(LogParserService::class);
        
        $this->app->singleton(ALBLogDownloaderInterface::class, function ($app) {
            $source = config('insights.alb_source', 'local');
            
            if ($source === 's3') {
                return new S3ALBLogDownloader(
                    $app->make(ALBLogAnalyzer::class),
                    $app->make(S3LogDownloaderService::class),
                    $app->make(LogParserService::class),
                    config('insights.sre_metrics_path')
                );
            }
            
            // Default: Local/Mock implementation
            return new ALBLogDownloader(
                $app->make(ALBLogAnalyzer::class),
                config('insights.sre_metrics_path')
            );
        });

        // Register application service (orchestration)
        $this->app->singleton(IncidentCorrelationService::class, function ($app) {
            return new IncidentCorrelationService(
                $app->make(LogParserService::class),
                $app->make(TrafficAnalyzerService::class),
                $app->make(IncidentMetricsCalculator::class),
                $app->make(WAFRuleGeneratorService::class)
            );
        });
    }
    
    public function boot() {

        if ($this->app->runningInConsole()) {
            $this->commands([
                DownloadALBLogsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/insights.php' => config_path('insights.php'),
            ], 'config');
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'insights');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}