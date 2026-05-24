<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\TourGenerationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::prefix('tours')->group(function () {
    Route::get('/', [TourController::class, 'index']);
    Route::get('/filters', [TourController::class, 'filters']);
    Route::get('/{tour:slug}', [TourController::class, 'show']);
});

Route::get('/categories', [CategoryController::class, 'index']);

// Admin-only LLM helper used from the Filament panel. We do not expose this
// endpoint to anonymous clients - the Filament form passes a signed admin
// session cookie when calling it. See AdminPanelProvider for the CSRF setup.
Route::middleware('web')->group(function () {
    Route::post('/admin/tours/generate', [TourGenerationController::class, 'generate']);
});
