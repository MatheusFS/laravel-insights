<?php

namespace MatheusFS\Laravel\Insights;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use MatheusFS\Laravel\Insights\Models\User\Pageview;

class Facade {

    public static function recordPageview(){

        return Pageview::create([
            'guard' => Auth::getDefaultDriver(),
            'user_id' => self::getId(),
            'browser' => $_SERVER['HTTP_USER_AGENT'],
            'page' => request()->url(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'none'
        ]);
    }

    public static function getId(){
        
        return Auth::check() ? Auth::id() : Session::getId();
    }
}