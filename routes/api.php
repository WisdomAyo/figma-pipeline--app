<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageProcessorController;
use App\Http\Controllers\FigmaTailwindController;
use App\Http\Controllers\FigmaAuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Process Figma image
    Route::post('/process-image', [ImageProcessorController::class, 'processImage'])->name('api.process-image');
    Route::post('figma/tailwind-config', [FigmaTailwindController::class, 'generate']);
});


