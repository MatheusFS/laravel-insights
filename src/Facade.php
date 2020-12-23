<?php

namespace MatheusFS\Laravel\Insights;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use MatheusFS\Laravel\Insights\Models\User\Pageview;

class Facade {

    public static function recordPageview(){

        return Pageview::create([
            'guard' => Auth::getDefaultDriver(),
            'user_id' => self::getId(),
            'ip_address' => self::getIp(),
            'browser' => $_SERVER['HTTP_USER_AGENT'],
            'page' => request()->url(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'none'
        ]);
    }

    public static function getId(){
        
        return Auth::check() ? Auth::id() : Session::getId();
    }

    /**
     * @return string
     */
    public static function getIp(){

        if (getenv('HTTP_CLIENT_IP')) $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR')) $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED')) $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR')) $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED')) $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR')) $ipaddress = getenv('REMOTE_ADDR');
        else if(app()->runningInConsole()) $ipaddress = 'CONSOLE';

        return $ipaddress ?? 'UNKNOWN';
    }
}