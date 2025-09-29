<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/debug-env', function () {
    return [
        'env' => env('FIGMA_API_TOKEN'),
        'config' => config('figma.api.token'),
    ];
});
