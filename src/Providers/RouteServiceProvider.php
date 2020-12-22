<?php

namespace MatheusFS\Laravel\Insights\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider {

    protected $namespace = 'MatheusFS\Laravel\Insights\Http\Controllers';

    public function boot() {

        parent::boot();
    }

    public function map() {
        
        $this->mapMainRoutes();
    }

    protected function mapMainRoutes() {

        Route::middleware(config('insights.middlewares'))
            ->name(config('insights.routes_name'))
            ->prefix(config('insights.routes_prefix'))
            ->namespace($this->namespace)
            ->group(__DIR__.'/../../routes/main.php');
    }
}
