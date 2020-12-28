<?php

namespace MatheusFS\Laravel\Insights\Support\Facades;

use Carbon\Carbon;
use MatheusFS\Laravel\Insights\Models\User\Pageview;

class Analytics {

    /**
     * Get pageview log data
     * 
     * @param \Illuminate\Database\Eloquent\Collection|static[] $collection Eloquent collection
     * @param string $query_string Search query string
     * @param integer $limit 
     * @param integer $take
     * @return \Illuminate\Support\Collection<MatheusFS\Laravel\Insights\Models\User\Pageview>
     */
    public static function getPageviews($collection, $query_string = '', $take = 25){
        
        if(!empty($query_string)){

            $collection = $collection->where('page', 'LIKE', "%$query_string%");
        }


        $pageviews = $collection->take($take);

        return response()->json([
            'keys' => $pageviews->keys(),
            'values' => $pageviews->values()
        ]);
    }
    
    /**
     * Get pageviews between a start and end date
     * 
     * @param Carbon $start_date
     * @param Carbon $end_date
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getPageviewsByStartEndDate($start_date, $end_date, $limit = 100000){
        
        ini_set('memory_limit', '2048M');
        
        $start = $start_date->toDateString();
        $end = $end_date->toDateString();

        return cache()->remember("insights:pageviews:$start:$end:$limit", Carbon::now()->addMinutes(30), function() use ($start_date, $end_date, $limit){
            
            return Pageview::select('created_at', 'page')
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->limit($limit)
            ->get()
            ->countBy(function($pageview){return preg_filter('/^(https?:\/\/)?(www\.)?(refresher\.com\.br|\w+)?\/(.*)$/', '/$4', $pageview->page);})
            ->sortByDesc(function($value){return $value;});
        });
    }

    /**
     * Route Tracker
     * 
     * You can use
     * request()->route()->getName()
     * 
     * To get the url you would use
     * request()->url()
     * 
     * And the path
     * request()->path()
     * 
     * Current route method
     * request->route()->getActionMethod()
     */
    public static function tracker(){

        return !empty(request()->route()) ? request()->route()->getName() : 'not-identified';
    }
}