<?php

namespace MatheusFS\Laravel\Insights;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use MatheusFS\Laravel\Insights\Providers\EventServiceProvider;
use MatheusFS\Laravel\Insights\Providers\RouteServiceProvider;

class ServiceProvider extends BaseServiceProvider {

    public function register() {

        $this->mergeConfigFrom(__DIR__.'/../config/insights.php', 'insights');
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
    }
    
    public function boot() {

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/insights.php' => config_path('insights.php'),
            ], 'config');
        
        }
        $this->loadViewsFrom(__DIR__.'/../resource/views', 'insights');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}