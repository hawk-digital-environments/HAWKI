<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\ApiAttachmentController;
use Illuminate\Http\Request;

use App\Models\User;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

    Route::post('attachments/upload', [ApiAttachmentController::class, 'upload']);
    Route::delete('attachments/{uuid}', [ApiAttachmentController::class, 'delete']);

    // ADD OTHER ENDPOINTS HERE


});