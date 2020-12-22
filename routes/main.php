<?php

use Illuminate\Support\Facades\Route;

Route::resource('pageview', 'PageviewController')->only('store');