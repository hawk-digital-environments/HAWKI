<?php

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