<?php

namespace MatheusFS\Laravel\Insights\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use MatheusFS\Laravel\Insights\Listeners\LogFailedLogin;
use MatheusFS\Laravel\Insights\Listeners\LogUserLogin;
use MatheusFS\Laravel\Insights\Listeners\RecordLogin;

class EventServiceProvider extends ServiceProvider {

    protected $listen = [
        Login::class => [
            RecordLogin::class, // Legacy listener (v1.0)
            LogUserLogin::class, // New listener (v2.0)
        ],
        Failed::class => [
            LogFailedLogin::class, // New listener (v2.0)
        ],
    ];

    public function boot(){

        parent::boot();
    }
}