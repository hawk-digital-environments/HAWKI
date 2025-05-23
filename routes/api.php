<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoomController;

use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;

use App\Models\User;

// routes/api.php
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    error_log('sanctum');
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

    


    // GROUPCHAT ROUTES

    Route::get('/user/rooms', [RoomController::class, 'getUserRooms']);
    Route::get('/room/{slug?}', [RoomController::class, 'loadRoom']);
    Route::post('/room/createRoom', [RoomController::class, 'createRoom']);
    Route::delete('/room/leaveRoom/{slug}', [RoomController::class, 'leaveRoom']);
    Route::post('/room/readstat/{slug}', [RoomController::class, 'markAsRead']);

    Route::middleware('roomEditor')->group(function () {
        Route::post('/room/sendMessage/{slug}', [RoomController::class, 'sendMessage']);
        Route::post('/room/updateMessage/{slug}', [RoomController::class, 'updateMessage']);
        Route::post('/room/streamAI/{slug}', [StreamController::class, 'handleAiConnectionRequest']);
    });

    Route::middleware('roomAdmin')->group(function () {
        Route::post('/room/addMember', [RoomController::class, 'addMember']);
        Route::post('/room/updateInfo/{slug}', [RoomController::class, 'updateInfo']);
        Route::delete('/room/removeRoom/{slug}', [RoomController::class, 'removeRoom']);
        Route::delete('/room/removeMember/{slug}', [RoomController::class, 'removeMember']);
    });


});