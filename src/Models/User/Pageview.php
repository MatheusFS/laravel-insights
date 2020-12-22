<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

class Pageview extends Model {
    
    protected $table = 'user_pageviews';

    protected $fillable = [
        'user_id',
        'browser',
        'screen_width',
        'screen_height',
        'page',
        'origin',
        'seconds_spent'
    ];
}
