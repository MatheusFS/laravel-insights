<?php

namespace MatheusFS\Laravel\Insights\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use MatheusFS\Laravel\Insights\Models\User\Pageview;
use MatheusFS\Laravel\Insights\Support\Facades\Analytics;

class PageviewController extends Controller {

    public function store(Request $request){
        
        $model_class = config('insights.user_model');
        $table = (new $model_class)->getTable();

        $request->validate([
            'pageview' => 'required',
            'pageview.user_id' => "required|exists:$table",
            'pageview.browser' => 'required',
            'pageview.screen_width' => 'required',
            'pageview.screen_height' => 'required',
            'pageview.page' => 'required',
            'pageview.referrer' => 'required',
            'pageview.seconds_spent' => 'required',
        ]);

        $pageview = Pageview::create($request->pageview);

        return response()->json($pageview);
    }

    public function update($pageview_id, Request $request){
    
        $pageview = Pageview::find($pageview_id)->update($request->pageview);

        return response()->json($pageview);
    }

    public function byStartEndDate($start, $end) {

        ini_set('memory_limit', '2048M');

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::parse($start),
            Carbon::parse($end)
        );

        return response()->json($collection);
    }

    public function all(Request $request) {

        ini_set('memory_limit', '1540M');

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::minValue(),
            Carbon::maxValue(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }

    public function thisYear(Request $request) {

        ini_set('memory_limit', '1540M');

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->startOfYear(),
            Carbon::now()->endOfYear(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }
    public function pastYear(Request $request) {

        ini_set('memory_limit', '1540M');

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->subYear(1)->startOfYear(),
            Carbon::now()->subYear(1)->endOfYear(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }

    public function thisMonth(Request $request) {

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }

    public function pastMonth(Request $request) {

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->subMonth(1)->startOfMonth(),
            Carbon::now()->subMonth(1)->endOfMonth(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }

    public function thisDay(Request $request) {

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }

    public function pastDay(Request $request) {

        $collection = Analytics::getPageviewsByStartEndDate(
            Carbon::now()->subDay(1)->startOfDay(),
            Carbon::now()->subDay(1)->endOfDay(),
            $request->limit
        );

        return Analytics::getPageviews($collection, $request->q, $request->take);
    }
}