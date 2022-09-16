<?php

namespace MatheusFS\Laravel\Insights\Listeners;

use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Support\Facades\Log;
use MatheusFS\Laravel\Insights\Models\User\Login;

class RecordLogin {

    public function handle(LoginEvent $event){

        /** @var \Illuminate\Database\Eloquent\Model */
        $model = $event->user;

        if(in_array(get_class($model), config('insights.ignore_models'))) return false;

        $user_id = $model->getKey();

        $user = config('insights.user_model')::find($user_id);

        $ip_address = $this->getIp();
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Agent inaccessable';

        $count = (isset($user->sessions) ? $user->sessions->count() : 0) + 1;

        Login::create([
            'guard' => $event->guard,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'browser' => $browser
        ]);

        Log::info("{$user->name} (#{$user_id}) logged in (IP: $ip_address; BROWSER: $browser). Now this user has $count simultaneous session(s).");
    }

    /**
     * @return string
     */
    public function getIp(){

        if (getenv('HTTP_CLIENT_IP')) $ip_address = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR')) $ip_address = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED')) $ip_address = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR')) $ip_address = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED')) $ip_address = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR')) $ip_address = getenv('REMOTE_ADDR');
        else if(app()->runningInConsole()) $ip_address = 'CONSOLE';

        return $ip_address ?? 'UNKNOWN';
    }
}