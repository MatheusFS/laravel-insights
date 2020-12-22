<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

class Login extends Model {

    const UPDATED_AT = null;

    protected $table = 'user_logins';
    
    protected $fillable = [
        'guard',
        'user_id',
        'ip_address',
        'browser'
    ];
}
