<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

class Pageview extends Model {

    const UPDATED_AT = null;
    const CREATED_AT = 'created_at';
    
    protected $table = 'user_pageviews';

    protected $fillable = [
        'guard',
        'user_id',
        'ip_address',
        'browser',
        'screen_width',
        'screen_height',
        'page',
        'referrer',
        'seconds_spent'
    ];

    protected $attributes = [
        'screen_width' => 0,
        'screen_height' => 0,
        'seconds_spent' => 0
    ];
}
