<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageProcessorController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Process Figma image
    Route::post('/process-image', [ImageProcessorController::class, 'processImage'])->name('api.process-image');
});
