<?php

namespace MatheusFS\Laravel\Insights\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use MatheusFS\Laravel\Insights\Listeners\RecordLogin;

class EventServiceProvider extends ServiceProvider {

    protected $listen = [
        Login::class => [
            RecordLogin::class,
        ]
    ];

    public function boot(){

        parent::boot();
    }
}