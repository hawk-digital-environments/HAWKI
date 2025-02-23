<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StreamController;


Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {
    Route::post('ext/ai-req', [StreamController::class, 'handleExternalRequest']);
});