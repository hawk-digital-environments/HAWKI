<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;

use App\Models\User;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

    // ADD OTHER ENDPOINTS HERE


});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('assistants', AssistantController::class);
    Route::post('assistants/{assistant}/remix', [AssistantController::class, 'remix']);
    Route::apiResource('categories', CategoryController::class)->only(['index']);
    Route::apiResource('languages', LanguageController::class)->only(['index']);
});