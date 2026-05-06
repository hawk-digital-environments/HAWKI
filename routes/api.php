<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AssistantLanguageController;
use App\Http\Controllers\AssistantReviewController;
use App\Http\Controllers\CategoryController;
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
    Route::post('assistants/{assistant}/release', [AssistantController::class, 'release']);
    Route::apiResource('assistant-review', AssistantReviewController::class)->only(['index', 'update'])->parameter('assistant-review', 'review');
    Route::apiResource('categories', CategoryController::class)->only(['index']);
    Route::apiResource('languages', AssistantLanguageController::class)->only(['index']);
});