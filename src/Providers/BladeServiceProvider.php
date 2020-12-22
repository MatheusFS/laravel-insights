<?php

namespace MatheusFS\Laravel\Insights\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider {

    public function boot(){

        Blade::include('insights::scripts.record_pageview', 'record_pageview');
    }
}