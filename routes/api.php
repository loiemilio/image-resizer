<?php

use Illuminate\Support\Facades\Route;

Route::post('/', 'Controller@upload')
    ->middleware(vsprintf('throttle:%d,%d', [
        config('resizer.throttling.allow'),
        config('resizer.throttling.every'),
    ]));

Route::get('{uuid}', 'Controller@show')
    ->where(['uuid' => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$']);

Route::delete('{uuid}', 'Controller@destroy')
    ->where(['uuid' => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$']);
