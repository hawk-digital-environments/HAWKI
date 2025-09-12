<?php

use App\Http\Controllers\EncryptionController;
use App\Http\Controllers\ExtAppController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SyncLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Routes for external apps
Route::middleware(['external_access:enabled,apps', 'auth:sanctum', 'app_access:enforcedToken'])
    ->attribute('prefix', 'apps')
    ->group(static function () {
        Route::group(['prefix' => 'connection'], static function () {
            Route::get('/{ext_user_id}', [ExtAppController::class, 'getConnection']);
            Route::post('/{ext_user_id}', [ExtAppController::class, 'createConnection']);
        });
    });

// Routes that do not allow apps
Route::middleware(['external_access:enabled', 'auth:sanctum', 'app_access:declined'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);
    
    Route::middleware(['external_access:chat'])->group(function () {
        Route::group(['prefix' => 'sync'], static function () {
            Route::get('/', [SyncLogController::class, 'index']);
        });
        
        Route::group(['prefix' => 'crypto'], static function () {
            Route::post('/keychain', [EncryptionController::class, 'backupKeychain']);
        });
        
        Route::group(['prefix' => 'rooms'], static function () {
            Route::post('/', [RoomController::class, 'create']);
            Route::post('/{slug}/messages/mark-read', [RoomController::class, 'markAsRead']);
            Route::delete('/{slug}/membership', [RoomController::class, 'leaveRoom']);
            
            Route::post('/invitation/accept', [InvitationController::class, 'onAcceptInvitation']);
            
            Route::middleware('roomEditor')->group(function () {
                Route::post('/{slug}/messages', [RoomController::class, 'sendMessage']);
                Route::put('/{slug}/messages', [RoomController::class, 'updateMessage']);
                Route::post('/{slug}/messages/stream-ai', [StreamController::class, 'handleAiConnectionRequest'])
                    ->defaults('external_app', true);
            });
            
            Route::middleware('roomAdmin')->group(function () {
                Route::put('/{slug}', [RoomController::class, 'update']);
                Route::delete('/{slug}', [RoomController::class, 'delete']);
                Route::delete('/{slug}/leave', [RoomController::class, 'leaveRoom']);
                Route::post('/{slug}/members', [RoomController::class, 'addMember']);
                Route::delete('/{slug}/members', [RoomController::class, 'kickMember']);
            });
        });
        
        Route::post('/inv/requestPublicKeys', [InvitationController::class, 'onRequestPublicKeys']);
        Route::post('/inv/store-invitations/{slug}', [InvitationController::class, 'storeInvitations']);
        Route::post('/inv/sendExternInvitation', [InvitationController::class, 'sendExternInvitationEmail']);
        Route::post('/inv/roomInvitationAccept', [InvitationController::class, 'onAcceptInvitation']);
        Route::get('/inv/requestInvitation/{slug}', [InvitationController::class, 'getInvitationWithSlug']);
        Route::get('/inv/requestUserInvitations', [InvitationController::class, 'getUserInvitations']);
    });
    
});
