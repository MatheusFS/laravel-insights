<?php

namespace MatheusFS\Laravel\Insights\Models\User;

use Illuminate\Database\Eloquent\Model;

class Search extends Model {
    
    protected $table = 'user_searches';

    protected $fillable = [
        // 'user_id',
        'query_string'
    ];
}
