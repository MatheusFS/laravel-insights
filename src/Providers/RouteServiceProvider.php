<?php

namespace MatheusFS\Laravel\Insights\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider {

    protected $namespace = 'MatheusFS\Laravel\Insights\Http\Controllers';

    public function boot() {

        parent::boot();
        
        // Laravel 10+: Chamar map() explicitamente
        $this->map();
    }

    public function map() {
        
        $this->mapMainRoutes();
        $this->mapApiRoutes();
    }

    protected function mapMainRoutes() {

        Route::middleware(config('insights.middlewares'))
            ->name(config('insights.routes_name'))
            ->prefix(config('insights.routes_prefix'))
            ->namespace($this->namespace)
            ->group(base_path('vendor/matheusfs/laravel-insights/routes/main.php'));
    }

    protected function mapApiRoutes() {

        Route::middleware('api')
            ->name('insights.reliability.')
            ->prefix('api/insights/reliability')
            ->namespace($this->namespace)
            ->group(base_path('vendor/matheusfs/laravel-insights/routes/api.php'));
    }
}
