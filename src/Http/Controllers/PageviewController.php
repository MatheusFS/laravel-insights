<?php

namespace MatheusFS\Laravel\Insights\Http\Controllers;

use Illuminate\Http\Request;
use MatheusFS\Laravel\Insights\Models\User\Pageview;

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
}