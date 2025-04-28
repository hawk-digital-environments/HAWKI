<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\AiConvController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EncryptionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ProfileController;

use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SessionExpiryChecker;


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
        Route::post('/req/profile/backupPassKey', [ProfileController::class, 'backupPassKey']);
        Route::get('/req/crypto/getServerSalt', [EncryptionController::class, 'getServerSalt']);
        Route::post('/req/complete_registration', [AuthenticationController::class, 'completeRegistration']);
    
    });
    

    Route::get('/check-session', [HomeController::class, 'CheckSessionTimeout']);

    //CHECKS USERS AUTH
    Route::middleware(['auth', 'expiry_check'])->group(function () {
    
        Route::get('/handshake', [AuthenticationController::class, 'handshake']);
    
        // AI CONVERSATION ROUTES
        Route::get('/chat', [HomeController::class, 'show']);
        Route::get('/chat/{slug?}' , [HomeController::class, 'show']);
    
        
        Route::get('/req/conv/{slug?}', [AiConvController::class, 'loadConv']);
        Route::post('/req/conv/createChat', [AiConvController::class, 'createConv']);
        Route::post('/req/conv/sendMessage/{slug}', [AiConvController::class, 'sendMessage']);
        Route::post('/req/conv/updateMessage/{slug}', [AiConvController::class, 'updateMessage']);
        Route::post('/req/conv/updateInfo/{slug}', [AiConvController::class, 'updateInfo']);
        Route::delete('/req/conv/removeConv/{slug}', [AiConvController::class, 'removeConv']);
    
    
        // GROUPCHAT ROUTES
        Route::get('/groupchat', [HomeController::class, 'show']);
        Route::get('/groupchat/{slug?}', [HomeController::class, 'show']);
    
        Route::get('/req/room/{slug?}', [RoomController::class, 'loadRoom']);
        Route::post('/req/room/createRoom', [RoomController::class, 'createRoom']);
        Route::delete('/req/room/leaveRoom/{slug}', [RoomController::class, 'leaveRoom']);
        Route::post('/req/room/readstat/{slug}', [RoomController::class, 'markAsRead']);
    
    
        Route::middleware('roomEditor')->group(function () {
            Route::post('/req/room/sendMessage/{slug}', [RoomController::class, 'sendMessage']);
            Route::post('/req/room/updateMessage/{slug}', [RoomController::class, 'updateMessage']);
            Route::post('/req/room/streamAI/{slug}', [StreamController::class, 'handleAiConnectionRequest']);
        });
    
        Route::middleware('roomAdmin')->group(function () {
            Route::post('/req/room/addMember', [RoomController::class, 'addMember']);
            Route::post('/req/room/updateInfo/{slug}', [RoomController::class, 'updateInfo']);
            Route::delete('/req/room/removeRoom/{slug}', [RoomController::class, 'removeRoom']);
            Route::delete('/req/room/removeMember/{slug}', [RoomController::class, 'removeMember']);
        });
    
        
        Route::get('print/{module}/{slug}', [HomeController::class, 'print']);
    
        // Profile
        Route::get('/profile', [HomeController::class, 'show']);
        Route::post('/req/profile/update', [ProfileController::class, 'update']);
        Route::get('/req/profile/requestPasskeyBackup', [ProfileController::class, 'requestPasskeyBackup']);
        
        // Token management routes with token_creation middleware
        Route::middleware('token_creation')->group(function () {
            Route::post('/req/profile/create-token', [AccessTokenController::class, 'createToken']);
            Route::get('/req/profile/fetch-tokens', [AccessTokenController::class, 'fetchTokenList']);
            Route::post('/req/profile/revoke-token', [AccessTokenController::class, 'revokeToken']);
        });
        
        Route::post('/req/profile/reset', [ProfileController::class, 'requestProfileRest']);
    
        // Invitation Handling
    
        // Route::post('/req/room/requestPublicKeys', [InvitationController::class, 'onRequestPublicKeys']);
        Route::post('/req/inv/store-invitations/{slug}', [InvitationController::class, 'storeInvitations']);
        Route::post('/req/inv/sendExternInvitation', [InvitationController::class, 'sendExternInvitationEmail']);
        Route::post('/req/inv/roomInvitationAccept',  [InvitationController::class, 'onAcceptInvitation']);
        Route::get('/req/inv/requestInvitation/{slug}',  [InvitationController::class, 'getInvitationWithSlug']);
        Route::get('/req/inv/requestUserInvitations',  [InvitationController::class, 'getUserInvitations']);
    
    
        Route::post('/req/downloadKeychain',  [EncryptionController::class, 'downloadKeychain']);
        Route::post('/req/backupKeychain',  [EncryptionController::class, 'backupKeychain']);
    
    
        // AI RELATED ROUTES
        Route::post('/req/streamAI', [StreamController::class, 'handleAiConnectionRequest']);
    
        Route::get('/req/search', [SearchController::class, 'search']);
    
  
    
    
    });
      // NAVIGATION ROUTES
      Route::get('/logout', [AuthenticationController::class, 'logout'])->name('logout');



});