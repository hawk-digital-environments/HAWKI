<?php

use App\Http\Controllers\Api\V1\AiCapabilityController;
use App\Http\Controllers\Api\V1\AiModelController;
use App\Http\Controllers\Api\V1\AiProviderController;
use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\ExtAppController;
use App\Http\Controllers\Api\V1\GenericChatController;
use App\Http\Controllers\Api\V1\McpServerController;
use App\Http\Controllers\Api\V1\MigrationController;
use App\Http\Controllers\Api\V1\RoomMemberController;
use App\Http\Controllers\Api\V1\RoomMessageController;
use App\Http\Controllers\Api\V1\SystemModelController;
use App\Http\Controllers\Api\V1\SystemPromptController;
use App\Http\Controllers\Api\V1\TranslationLabelController;
use App\Http\Controllers\Api\V1\UserKeychainValueController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\StorageProxyController;
use App\Http\Controllers\StreamController;
use App\Http\Middleware\Api\ApiDataScopeContextSettingMiddleware;
use App\Http\Middleware\Api\BlockExtAppsIfNotAllowedMiddleware;
use App\Http\Middleware\ExtApp\AppTokenForbiddenMiddleware;
use App\Http\Middleware\ExtApp\ExtAppUserOrTokenForbiddenMiddleware;
use App\Http\Middleware\ExternalAccessRequiredMiddleware;
use App\JsonApi\V1\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Routing\ActionRegistrar;
use LaravelJsonApi\Laravel\Routing\Relationships;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;

Route::middleware(['auth:sanctum', 'deprecated:/api/hawki/v1/users/me'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware([
    ExternalAccessRequiredMiddleware::class,
    'auth:sanctum',
    BlockExtAppsIfNotAllowedMiddleware::class,
    AppTokenForbiddenMiddleware::class
])->group(function () {
    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

    Route::group(['prefix' => Server::BASE_URL_PREFIX], static function () {
    });
});

Route::middleware([
    'auth:sanctum',
    BlockExtAppsIfNotAllowedMiddleware::class,
    AppTokenForbiddenMiddleware::class
])->group(function () {
    Route::group(['prefix' => Server::BASE_URL_PREFIX], static function () {

        Route::get('/proxy/storage/{identifier}', [StorageProxyController::class, 'streamRouted'])
            ->where(['identifier' => '.*']);

    });
});

JsonApiRoute::server('v1')
    ->prefix(Server::BASE_URL_PREFIX)
    ->middleware(
        BlockExtAppsIfNotAllowedMiddleware::class,
        AppTokenForbiddenMiddleware::class,
        ApiDataScopeContextSettingMiddleware::class,
    )
    ->resources(function (ResourceRegistrar $server) {
        $server->resource('connections', ConnectionController::class)
            ->withoutMiddleware(AppTokenForbiddenMiddleware::class)
            ->only('show');

        $server->resource('migrations', MigrationController::class)
            ->actions(function (ActionRegistrar $actions) {
                $actions->post('actions/apply', 'markMigrationAsApplied');
            })
            ->only('index', 'show');

        $server->resource('ext-apps', ExtAppController::class)
            ->withoutMiddleware(ExternalAccessRequiredMiddleware::class)
            ->middleware(ExtAppUserOrTokenForbiddenMiddleware::class)
            ->only('show')
            ->actions(function (ActionRegistrar $actions) {
                $actions->post('actions/establish-connection', 'establishConnection');
                $actions->get('actions/proxy-logo/{appId}', 'logoProxy')
                    ->name('proxyLogo')
                    ->middleware('signed');
            });

        $server->resource('configs', ConfigController::class)
            ->only('show');

        $server->resource('translation-labels', TranslationLabelController::class)
            ->only('show');

        $server->resource('mcp-servers', McpServerController::class)
            ->only('index', 'show')
            ->relationships(function ($relationships) {
                $relationships->hasMany('tools')->readOnly();
            });

        $server->resource('ai-tools', AiToolController::class)
            ->only('index', 'show')
            ->relationships(function ($relationships) {
                $relationships->hasOne('server')->readOnly();
                $relationships->hasMany('models')->readOnly();
            });

        $server->resource('ai-tool-capabilities', AiCapabilityController::class)
            ->only('index');

        $server->resource('ai-providers', AiProviderController::class)
            ->only('index', 'show')
            ->relationships(function ($relationships) {
                $relationships->hasMany('models')->readOnly();
            });

        $server->resource('ai-models', AiModelController::class)
            ->only('index', 'show')
            ->relationships(function ($relationships) {
                $relationships->hasOne('provider')->readOnly();
                $relationships->hasMany('tools')->readOnly();
            });

        $server->resource('system-models', SystemModelController::class)
            ->readOnly()
            ->relationships(function (Relationships $relationships) {
                $relationships->hasOne('model')->readOnly();
            });

        $server->resource('system-prompts', SystemPromptController::class)
            ->readOnly();

        $server->resource('users', UsersController::class)
            ->readOnly()
            ->actions(function (ActionRegistrar $actions) {
                $actions->get('me', 'handleMe')
                    ->withoutMiddleware(AppTokenForbiddenMiddleware::class);
            });

        $server->resource('user-keychain-values', UserKeychainValueController::class)
            ->actions(function (ActionRegistrar $actions) {
                $actions->get('actions/validator', 'getPasskeyValidator')
                    ->name('validator');
                $actions->post('actions/batch-update', 'batchUpdate')
                    ->name('batchUpdate');
            })
            ->readOnly();

        $server->resource('rooms', \App\Http\Controllers\Api\V1\RoomController::class)
            ->readOnly();

        $server->resource('room-messages', RoomMessageController::class)
            ->readOnly();

        $server->resource('room-members', RoomMemberController::class)
            ->readOnly();
    });
