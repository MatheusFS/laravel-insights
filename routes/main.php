<?php

use Illuminate\Support\Facades\Route;

Route::resource('pageviews', 'PageviewController')->only(['store', 'update']);

Route::get('/pageviews/day', 'PageviewController@thisDay')->name('pageviews.day');
Route::get('/pageviews/past_day', 'PageviewController@pastDay')->name('pageviews.past_day');

Route::get('/pageviews/month', 'PageviewController@thisMonth')->name('pageviews.month');
Route::get('/pageviews/past_month', 'PageviewController@pastMonth')->name('pageviews.past_month');

Route::get('/pageviews/year', 'PageviewController@thisYear')->name('pageviews.year');
Route::get('/pageviews/past_year', 'PageviewController@pastYear')->name('pageviews.past_year');

Route::get('/pageviews/all', 'PageviewController@all')->name('pageviews.all');

Route::get('/pageviews/{start}/{end}', 'PageviewController@byStartEndDate')->name('pageviews.period');