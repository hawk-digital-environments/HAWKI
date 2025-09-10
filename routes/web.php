<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AiConvController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EncryptionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\ProfileController;


Route::middleware('prevent_back')->group(function () {

    Route::get('/', [LoginController::class, 'index']);

    Route::get('/login', [LoginController::class, 'index']);
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
        Route::get('/req/crypto/getServerSalt', [EncryptionController::class, 'getServerSalt']);
        Route::post('/req/complete_registration', [AuthenticationController::class, 'completeRegistration']);

    });


    Route::get('/check-session', [HomeController::class, 'CheckSessionTimeout']);


    // Announcement routes
    Route::get('/req/announcement/render/{id}', [AnnouncementController::class, 'render']);
    Route::post('/req/announcement/seen/{id}', [AnnouncementController::class, 'markSeen']);
    Route::post('/req/announcement/report/{id}', [AnnouncementController::class, 'submitReport']);
    Route::get('/req/announcement/fetchLatestPolicy', [AnnouncementController::class, 'fetchLatestPolicy']);


    //CHECKS USERS AUTH
    Route::middleware(['auth', 'expiry_check'])->group(function () {

        Route::get('/handshake', [AuthenticationController::class, 'handshake']);

        // AI CONVERSATION ROUTES
        Route::get('/chat', [HomeController::class, 'index']);
        Route::get('/groupchat', [HomeController::class, 'index']);



        Route::middleware('signature_check')->group(function(){

            Route::get('/chat/{slug?}' , [HomeController::class, 'index']);

            Route::get('/req/conv/{slug?}', [AiConvController::class, 'load']);
            Route::post('/req/conv/createChat', [AiConvController::class, 'create']);
            Route::post('/req/conv/sendMessage/{slug}', [AiConvController::class, 'sendMessage']);
            Route::post('/req/conv/updateMessage/{slug}', [AiConvController::class, 'updateMessage']);
            Route::post('/req/conv/updateInfo/{slug}', [AiConvController::class, 'update']);
            Route::delete('/req/conv/removeConv/{slug}', [AiConvController::class, 'delete']);

            Route::delete('/req/conv/message/delete/{slug}', [AiConvController::class, 'deleteMessage']);

            Route::post('/req/conv/attachment/upload', [AiConvController::class, 'storeAttachment']);
            Route::get('/req/conv/attachment/getLink/{uuid}', [AiConvController::class, 'getAttachmentUrl']);

            Route::get('/files/{uuid}/private/{path}', [AiConvController::class, 'downloadAttachment'])
                ->where([
                    'path' => '.*'
                ])->name('files.download.private')->middleware('signed');


            Route::delete('/req/conv/attachment/delete', [AiConvController::class, 'deleteAttachment']);
            Route::post('/req/streamAI', [StreamController::class, 'handleAiConnectionRequest']);


            // GROUPCHAT ROUTES
            Route::get('/groupchat/{slug?}', [HomeController::class, 'index']);

            Route::get('/req/room/{slug?}', [RoomController::class, 'load']);
            Route::post('/req/room/createRoom', [RoomController::class, 'create']);

            Route::delete('/req/room/leaveRoom/{slug}', [RoomController::class, 'leaveRoom']);
            Route::post('/req/room/readstat/{slug}', [RoomController::class, 'markAsRead']);
            Route::get('/req/room/message/get/{slug}/{messageId}', [RoomController::class, 'retrieveMessage']);
            Route::get('/req/room/attachment/getLink/{uuid}', [RoomController::class, 'getAttachmentUrl']);
            Route::get('/files/{uuid}/group/{path}', [RoomController::class, 'downloadAttachment'])
                ->where([
                    'path' => '.*'
                ])->name('files.download.group')->middleware('signed');

            Route::middleware('roomEditor')->group(function () {
                Route::post('/req/room/sendMessage/{slug}', [RoomController::class, 'sendMessage']);
                Route::post('/req/room/updateMessage/{slug}', [RoomController::class, 'updateMessage']);
                Route::post('/req/room/streamAI/{slug}', [StreamController::class, 'handleAiConnectionRequest']);

                Route::post('/req/room/attachment/upload/{slug}', [RoomController::class, 'storeAttachment']);
            });

            Route::middleware('roomAdmin')->group(function () {
                Route::post('/req/room/updateInfo/{slug}', [RoomController::class, 'update']);
                Route::post('/req/room/uploadAvatar/{slug}', [RoomController::class, 'uploadAvatar']);
                Route::delete('/req/room/removeRoom/{slug}', [RoomController::class, 'delete']);
                Route::post('/req/room/addMember/{slug}', [RoomController::class, 'addMember']);
                Route::delete('/req/room/removeMember/{slug}', [RoomController::class, 'kickMember']);
            });
            Route::delete('/req/room/attachment/delete', [RoomController::class, 'deleteAttachment']);

            Route::post('/req/room/search', [RoomController::class, 'searchUser']);

            Route::get('print/{module}/{slug}', [HomeController::class, 'print']);

                    // Invitation Handling

            // Route::post('/req/room/requestPublicKeys', [InvitationController::class, 'onRequestPublicKeys']);
            Route::post('/req/inv/store-invitations/{slug}', [InvitationController::class, 'storeInvitations']);
            Route::post('/req/inv/sendExternInvitation', [InvitationController::class, 'sendExternInvitationEmail']);
            Route::post('/req/inv/roomInvitationAccept',  [InvitationController::class, 'onAcceptInvitation']);
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
        Route::get('/profile', [HomeController::class, 'index']);
        Route::post('/req/profile/update', [ProfileController::class, 'update']);
        Route::post('/req/profile/uploadAvatar', [ProfileController::class, 'uploadAvatar']);
        Route::get('/req/profile/requestPasskeyBackup', [ProfileController::class, 'requestPasskeyBackup']);

        Route::post('/req/profile/reset', [ProfileController::class, 'requestProfileRest']);

        Route::post('/req/downloadKeychain',  [EncryptionController::class, 'downloadKeychain']);
        Route::post('/req/backupKeychain',  [EncryptionController::class, 'backupKeychain']);


        // AI RELATED ROUTES
    });
    // NAVIGATION ROUTES
    Route::get('/logout', [AuthenticationController::class, 'logout'])->name('logout');



});
