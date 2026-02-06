<?php

use App\Http\Controllers\ExtAppController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StorageProxyController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\UserKeychainController;
use App\Http\Middleware\SyncLogResponseEnrichingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Routes for external apps (with enforced app access -> Meaning the user requesting is the app itself not a regular user)
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
    
    // The route collection for external apps
    Route::middleware([
        'external_access:enabled,apps',
        SyncLogResponseEnrichingMiddleware::class
    ])
        ->attribute('prefix', 'apps')
        ->group(function () {
            Route::group(['prefix' => 'sync'], static function () {
                Route::get('/', [SyncLogController::class, 'index'])
                    ->name('api.external_app.syncLog');
            });
            
            Route::group(['prefix' => 'keychain'], static function () {
                Route::post('/', [UserKeychainController::class, 'update'])
                    ->name('api.external_app.keychainUpdate');
                Route::get('/validator', [UserKeychainController::class, 'getPasskeyValidator'])
                    ->name('api.external_app.keychainPasskeyValidator');
            });
            
            Route::get('/proxy/storage/{category}/{filename}', [StorageProxyController::class, 'streamRouted'])
                ->where(['filename' => '.*'])
                ->name('api.external_app.storage.proxy');
            
            Route::group(['prefix' => 'profile'], static function () {
                Route::put('/', [ProfileController::class, 'update'])
                    ->name('api.external_app.profileUpdate');
                Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])
                    ->name('api.external_app.profileAvatarUpload');
            });
            
            Route::group(['prefix' => 'rooms'], static function () {
                Route::post('/', [RoomController::class, 'create'])
                    ->name('api.external_app.roomCreate');
                Route::post('/{slug}/messages/mark-read', [RoomController::class, 'markAsRead'])
                    ->name('api.external_app.roomMessagesMarkRead');
                Route::delete('/{slug}/membership', [RoomController::class, 'leaveRoom'])
                    ->name('api.external_app.roomLeave');
                
                Route::post('/invitation/accept', [InvitationController::class, 'onAcceptInvitation'])
                    ->name('api.external_app.roomInvitationAccept');
                
                Route::middleware('roomEditor')->group(function () {
                    Route::post('/{slug}/messages', [RoomController::class, 'sendMessage'])
                        ->name('api.external_app.roomMessagesSend');
                    Route::post('/{slug}/messages/attachments', [RoomController::class, 'storeAttachment'])
                        ->name('api.external_app.roomMessagesAttachmentUpload');
                    Route::put('/{slug}/messages', [RoomController::class, 'updateMessage'])
                        ->name('api.external_app.roomMessagesEdit');
                    Route::post('/{slug}/messages/stream-ai', [StreamController::class, 'handleAiConnectionRequest'])
                        ->middleware('external_access:apps_groups_ai')
                        ->name('api.external_app.roomMessagesAiSend');
                });
                
                Route::middleware('roomAdmin')->group(function () {
                    Route::put('/{slug}', [RoomController::class, 'update'])
                        ->name('api.external_app.roomUpdate');
                    Route::post('/{slug}/avatar', [RoomController::class, 'uploadAvatar'])
                        ->name('api.external_app.roomAvatarUpload');
                    Route::delete('/{slug}', [RoomController::class, 'delete'])
                        ->name('api.external_app.roomRemove');
                    
                    Route::post('/{slug}/member-candidate-search', [RoomController::class, 'searchUser'])
                        ->name('api.external_app.roomMemberCandidateSearch');
                    Route::post('/{slug}/members', [InvitationController::class, 'storeInvitations'])
                        ->name('api.external_app.roomInviteMember');
                    Route::put('/{slug}/members', [RoomController::class, 'addMember'])
                        ->name('api.external_app.roomEditMember');
                    Route::delete('/{slug}/members', [RoomController::class, 'kickMember'])
                        ->name('api.external_app.roomRemoveMember');
                });
            });
        });
});
