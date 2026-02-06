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

            $this->publishes([
                __DIR__.'/../config/insights.php' => config_path('insights.php'),
            ], 'config');
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'insights');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}