<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

class Login extends Model {

    const UPDATED_AT = null;

    protected $touches = ['user'];

    protected $table = 'user_logins';
    
    protected $fillable = [
        'guard',
        'user_id',
        'ip_address',
        'browser'
    ];

    public function user(){ return $this->belongsTo(config('insights.user_model')); }
}
