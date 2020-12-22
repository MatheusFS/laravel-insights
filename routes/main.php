<?php

use Illuminate\Support\Facades\Route;

Route::resource('pageviews', 'PageviewController')->only(['store', 'update']);