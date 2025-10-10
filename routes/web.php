<?php

use App\Http\Controllers\AiConvController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\ExtAppController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StorageProxyController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\UserKeychainController;
use App\Http\Middleware\SyncLogResponseEnrichingMiddleware;
use Illuminate\Support\Facades\Route;


Route::middleware(['prevent_back', 'app_access:declined'])->group(function () {
    
    Route::get('/', [LoginController::class, 'index']);
    
    Route::get('/login', [LoginController::class, 'index'])->name('login');


    Route::post('/req/login-ldap', [AuthenticationController::class, 'ldapLogin']);
    Route::post('/req/login-shibboleth', [AuthenticationController::class, 'shibbolethLogin']);
    Route::post('/req/login-oidc', [AuthenticationController::class, 'openIDLogin']);


    Route::post('/req/changeLanguage', [LanguageController::class, 'changeLanguage']);

    Route::get('/inv/{tempHash}/{slug}', [InvitationController::class, 'openExternInvitation'])->name('open.invitation')->middleware('signed');

    Route::get('/dataprotection',[HomeController::class, 'dataprotectionIndex']);


    Route::middleware('registrationAccess')->group(function () {

        Route::get('/register', [AuthenticationController::class, 'register']);
        Route::post('/req/profile/validatePasskey', [ProfileController::class, 'validatePasskey']);
        Route::post('/req/profile/backupPassKey', [ProfileController::class, 'backupPassKey']);
        Route::get('/req/crypto/getServerSalt', [ProfileController::class, 'getServerSalt']);
        Route::post('/req/complete_registration', [AuthenticationController::class, 'completeRegistration']);

    });


    Route::get('/check-session', [HomeController::class, 'CheckSessionTimeout']);


    // Announcement routes
    Route::get('/req/announcement/render/{id}', [AnnouncementController::class, 'render']);
    Route::post('/req/announcement/seen/{id}', [AnnouncementController::class, 'markSeen']);
    Route::post('/req/announcement/report/{id}', [AnnouncementController::class, 'submitReport']);
    Route::get('/req/announcement/fetchLatestPolicy', [AnnouncementController::class, 'fetchLatestPolicy']);


    //CHECKS USERS AUTH
    Route::middleware([
        'auth',
        'expiry_check',
        SyncLogResponseEnrichingMiddleware::class
    ])->group(function () {
        
        Route::group(['prefix' => 'web-api'], static function () {
            Route::get('/sync', [SyncLogController::class, 'index'])
                ->name('web.syncLog');
        });
        
        Route::get('/handshake', [AuthenticationController::class, 'handshake']);
        
        // AI CONVERSATION ROUTES
        Route::get('/chat', [HomeController::class, 'index'])
            ->middleware('handle_app_connect');
        Route::get('/groupchat', [HomeController::class, 'index'])
            ->middleware('handle_app_connect');



        Route::middleware('signature_check')->group(function(){
            
            // STORAGE PROXY
            Route::get('/proxy/storage/{category}/{filename}', [StorageProxyController::class, 'streamRouted'])
                ->where(['filename' => '.*'])
                ->name('web.storage.proxy');
            
            Route::get('/chat/{slug?}', [HomeController::class, 'index'])
                ->middleware('handle_app_connect');
            
            Route::get('/req/conv/{slug?}', [AiConvController::class, 'load'])
                ->middleware('handle_app_connect');
            Route::post('/req/conv/createChat', [AiConvController::class, 'create']);
            Route::post('/req/conv/sendMessage/{slug}', [AiConvController::class, 'sendMessage']);
            Route::post('/req/conv/updateMessage/{slug}', [AiConvController::class, 'updateMessage']);
            Route::post('/req/conv/updateInfo/{slug}', [AiConvController::class, 'update']);
            Route::delete('/req/conv/removeConv/{slug}', [AiConvController::class, 'delete']);

            Route::delete('/req/conv/message/delete/{slug}', [AiConvController::class, 'deleteMessage']);

            Route::post('/req/conv/attachment/upload', [AiConvController::class, 'storeAttachment']);
            Route::get('/req/conv/attachment/getLink/{uuid}', [AiConvController::class, 'getAttachmentUrl']);

            Route::delete('/req/conv/attachment/delete', [AiConvController::class, 'deleteAttachment']);
            Route::post('/req/streamAI', [StreamController::class, 'handleAiConnectionRequest']);


            // GROUPCHAT ROUTES
            Route::get('/groupchat/{slug?}', [HomeController::class, 'index'])
                ->middleware('handle_app_connect');

            Route::get('/req/room/{slug?}', [RoomController::class, 'load']);
            Route::post('/req/room/createRoom', [RoomController::class, 'create'])
                ->name('web.roomCreate');
            
            Route::delete('/req/room/leaveRoom/{slug}', [RoomController::class, 'leaveRoom'])
                ->name('web.roomLeave');
            Route::post('/req/room/readstat/{slug}', [RoomController::class, 'markAsRead'])
                ->name('web.roomMessagesMarkRead');
            Route::get('/req/room/message/get/{slug}/{messageId}', [RoomController::class, 'retrieveMessage']);
            Route::get('/req/room/attachment/getLink/{uuid}', [RoomController::class, 'getAttachmentUrl']);

            Route::middleware('roomEditor')->group(function () {
                Route::post('/req/room/sendMessage/{slug}', [RoomController::class, 'sendMessage'])
                    ->name('web.roomMessagesSend');
                Route::post('/req/room/updateMessage/{slug}', [RoomController::class, 'updateMessage'])
                    ->name('web.roomMessagesEdit');
                Route::post('/req/room/streamAI/{slug}', [StreamController::class, 'handleAiConnectionRequest'])
                    ->name('web.roomMessagesAiSend');
                
                Route::post('/req/room/attachment/upload/{slug}', [RoomController::class, 'storeAttachment'])
                    ->name('web.roomMessagesAttachmentUpload');
            });

            Route::middleware('roomAdmin')->group(function () {
                Route::post('/req/room/updateInfo/{slug}', [RoomController::class, 'update'])
                    ->name('web.roomUpdate');
                Route::post('/req/room/uploadAvatar/{slug}', [RoomController::class, 'uploadAvatar'])
                    ->name('web.roomAvatarUpload');
                Route::delete('/req/room/removeRoom/{slug}', [RoomController::class, 'delete'])
                    ->name('web.roomRemove');
                Route::post('/req/room/addMember/{slug}', [RoomController::class, 'addMember'])
                    ->name('web.roomEditMember');
                Route::delete('/req/room/removeMember/{slug}', [RoomController::class, 'kickMember'])
                    ->name('web.roomRemoveMember');
            });
            Route::delete('/req/room/attachment/delete', [RoomController::class, 'deleteAttachment']);
            
            Route::post('/req/room/search', [RoomController::class, 'searchUser'])
                ->name('web.roomMemberCandidateSearch');

            Route::get('print/{module}/{slug}', [HomeController::class, 'print']);

                    // Invitation Handling

            // Route::post('/req/room/requestPublicKeys', [InvitationController::class, 'onRequestPublicKeys']);
            Route::post('/req/inv/store-invitations/{slug}', [InvitationController::class, 'storeInvitations'])
                ->name('web.roomInviteMember');
            Route::post('/req/inv/sendExternInvitation', [InvitationController::class, 'sendExternInvitationEmail']);
            Route::post('/req/inv/roomInvitationAccept', [InvitationController::class, 'onAcceptInvitation'])
                ->name('web.roomInvitationAccept');
            Route::get('/req/inv/requestInvitation/{slug}',  [InvitationController::class, 'getInvitationWithSlug']);
            Route::get('/req/inv/requestUserInvitations',  [InvitationController::class, 'getUserInvitations']);


            // Token management routes with token_creation middleware
            Route::middleware('token_creation')->group(function () {
                Route::post('/req/profile/create-token', [ProfileController::class, 'requestApiToken']);
                Route::get('/req/profile/fetch-tokens', [ProfileController::class, 'fetchTokenList']);
                Route::post('/req/profile/revoke-token', [ProfileController::class, 'revokeToken']);
            });
        });

        // Profile
        Route::get('/profile', [HomeController::class, 'index'])
            ->middleware('handle_app_connect');
        Route::post('/req/profile/update', [ProfileController::class, 'update'])
            ->name('web.profileUpdate');
        Route::post('/req/profile/uploadAvatar', [ProfileController::class, 'uploadAvatar'])
            ->name('web.profileAvatarUpload');
        Route::get('/req/profile/requestPasskeyBackup', [ProfileController::class, 'requestPasskeyBackup']);
        
        Route::post('/req/profile/reset', [ProfileController::class, 'requestProfileReset']);
        
        Route::get('/keychain', [UserKeychainController::class, 'list'])
            ->name('web.keychainList');
        Route::get('/keychain/legacy', [UserKeychainController::class, 'getLegacyKeychain'])
            ->name('web.keychainLegacy');
        Route::post('/keychain/markAsMigrated', [UserKeychainController::class, 'markAsMigrated'])
            ->name('web.keychainMarkAsMigrated');
        Route::get('/keychain/validator', [UserKeychainController::class, 'getPasskeyValidator'])
            ->name('web.keychainPasskeyValidator');
        Route::post('/keychain', [UserKeychainController::class, 'update'])
            ->name('web.keychainUpdate');

        // AI RELATED ROUTES
    });
    // NAVIGATION ROUTES
    Route::get('/logout', [AuthenticationController::class, 'logout'])->name('logout');
    
    // Routes for external apps
    Route::middleware(['external_access:enabled,apps', 'app_access:declined'])
        ->group(static function () {
            Route::get('/proxy/app-logo/{app_id}', [ExtAppController::class, 'appLogoProxy'])
                ->name('apps.logo');
            
            Route::group(['prefix' => 'apps'], static function () {
                Route::group(['prefix' => 'connect'], static function () {
                    Route::middleware(['auth', 'expiry_check', 'app_user_request_required'])->group(static function () {
                        Route::get('/confirm', [ExtAppController::class, 'confirmAppConnectRequest'])->name('web.apps.confirm');
                        Route::post('/confirm/accept', [ExtAppController::class, 'acceptAppConnectRequestAction'])->name('web.apps.confirm.accept');
                        Route::post('/confirm/decline', [ExtAppController::class, 'declineAppConnectRequestAction'])->name('web.apps.confirm.decline');
                    });
                    
                    Route::get('/{request_id}', [ExtAppController::class, 'receiveAppConnectRequest'])->name('web.apps.connect');
                });
            });
        });
});
