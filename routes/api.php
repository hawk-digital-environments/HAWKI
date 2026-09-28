<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AiCapabilityController;
use App\Http\Controllers\Api\V1\AiConvController;
use App\Http\Controllers\Api\V1\AiModelController;
use App\Http\Controllers\Api\V1\AiModelDescriptionController;
use App\Http\Controllers\Api\V1\AiModelFlagController;
use App\Http\Controllers\Api\V1\AiProviderController;
use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\ExtAppController;
use App\Http\Controllers\Api\V1\McpServerController;
use App\Http\Controllers\Api\V1\MigrationController;
use App\Http\Controllers\Api\V1\RoomMemberController;
use App\Http\Controllers\Api\V1\RoomMessageController;
use App\Http\Controllers\Api\V1\SystemModelController;
use App\Http\Controllers\Api\V1\SystemPromptController;
use App\Http\Controllers\Api\V1\TranslationLabelController;
use App\Http\Controllers\Api\V1\UserKeychainValueController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\Api\V1\UserSettingController;
use App\Http\Controllers\LinkPreviewController;
use App\Http\Controllers\StorageProxyController;
use App\Http\Controllers\StreamController;
use App\Http\Middleware\Api\ApiDataScopeContextSettingMiddleware;
use App\Http\Middleware\Api\BlockExtAppsIfNotAllowedMiddleware;
use App\Http\Middleware\ExtApp\AppTokenForbiddenMiddleware;
use App\Http\Middleware\ExtApp\ExtAppUserOrTokenForbiddenMiddleware;
use App\Http\Middleware\ExternalAccessRequiredMiddleware;
use App\JsonApi\V1\Server;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Routing\ActionRegistrar;
use LaravelJsonApi\Laravel\Routing\Relationships;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;

Route::middleware(['auth:sanctum', 'deprecated:/api/hawki/v1/users/me'])->get('/user', static function (Request $request) {
    return $request->user();
});

Route::middleware([
    ExternalAccessRequiredMiddleware::class,
    'auth:sanctum',
    BlockExtAppsIfNotAllowedMiddleware::class,
    AppTokenForbiddenMiddleware::class,
])->group(static function (): void {
    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);
});

Route::middleware([
    'auth:sanctum',
    BlockExtAppsIfNotAllowedMiddleware::class,
    AppTokenForbiddenMiddleware::class,
])->group(static function (): void {
    Route::group(['prefix' => Server::BASE_URL_PREFIX], static function (): void {
        Route::get('/proxy/link-preview/favicon', [LinkPreviewController::class, 'getFavicon'])
            ->name('api.link-preview.favicon');
        Route::get('/proxy/link-preview/image', [LinkPreviewController::class, 'getImage'])
            ->name('api.link-preview.image');
        Route::get('/proxy/link-preview/metadata', [LinkPreviewController::class, 'getPreview']);

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
    ->withoutMiddleware(ConvertEmptyStringsToNull::class)
    ->resources(static function (ResourceRegistrar $server): void {
        $server->resource('connections', ConnectionController::class)
            ->withoutMiddleware(AppTokenForbiddenMiddleware::class)
            ->only('show');

        $server->resource('migrations', MigrationController::class)
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->post('actions/apply', 'markMigrationAsApplied');
            })
            ->only('index', 'show');

        $server->resource('ext-apps', ExtAppController::class)
            ->withoutMiddleware(ExternalAccessRequiredMiddleware::class)
            ->middleware(ExtAppUserOrTokenForbiddenMiddleware::class)
            ->only('show')
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->post('actions/establish-connection', 'establishConnection');
                $actions->get('actions/proxy-logo/{appId}', 'logoProxy')
                    ->name('proxyLogo')
                    ->middleware('signed');
            });

        $server->resource('configs', ConfigController::class)
            ->only('show');

        $server->resource('user-settings', UserSettingController::class)
            ->only('index', 'show', 'store', 'update');

        $server->resource('translation-labels', TranslationLabelController::class)
            ->only('show');

        $server->resource('mcp-servers', McpServerController::class)
            ->only('index', 'show')
            ->relationships(static function ($relationships): void {
                $relationships->hasMany('tools')->readOnly();
            });

        $server->resource('ai-tools', AiToolController::class)
            ->only('index', 'show')
            ->relationships(static function ($relationships): void {
                $relationships->hasOne('server')->readOnly();
                $relationships->hasMany('models')->readOnly();
            });

        $server->resource('ai-tool-capabilities', AiCapabilityController::class)
            ->only('index');

        $server->resource('ai-providers', AiProviderController::class)
            ->only('index', 'show')
            ->relationships(static function ($relationships): void {
                $relationships->hasMany('models')->readOnly();
            });

        $server->resource('ai-models', AiModelController::class)
            ->only('index', 'show')
            ->relationships(static function ($relationships): void {
                $relationships->hasOne('provider')->readOnly();
                $relationships->hasMany('tools')->readOnly();
            });

        $server->resource('ai-model-flags', AiModelFlagController::class)
            ->readOnly();

        $server->resource('ai-model-descriptions', AiModelDescriptionController::class)
            ->readOnly();

        $server->resource('system-models', SystemModelController::class)
            ->readOnly()
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('model')->readOnly();
            });

        $server->resource('system-prompts', SystemPromptController::class)
            ->readOnly();

        $server->resource('users', UsersController::class)
            ->only('index', 'show', 'update')
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->get('me', 'handleMe')
                    ->withoutMiddleware(AppTokenForbiddenMiddleware::class);
                $actions->post('actions/avatar', 'uploadAvatar');
                $actions->post('actions/reset-profile', 'resetProfile');
            });

        $server->resource('user-keychain-values', UserKeychainValueController::class)
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->get('actions/validator', 'getPasskeyValidator')
                    ->name('validator');
                $actions->post('actions/batch-update', 'batchUpdate')
                    ->name('batchUpdate');
            })
            ->readOnly();

        $server->resource('rooms', \App\Http\Controllers\Api\V1\RoomController::class)
            ->readOnly();

        $server->resource('ai-convs', AiConvController::class)
            ->only('index', 'show', 'store', 'update', 'destroy')
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->post('actions/attachments', 'storeAttachment');
                $actions->delete('actions/attachments/{uuid}', 'deleteAttachment');
                $actions->withId()->post('actions/messages', 'storeMessage');
                $actions->withId()->patch('actions/messages/{messageId}', 'updateMessage');
                $actions->withId()->delete('actions/messages/{messageId}', 'deleteMessage');
            });

        $server->resource('room-messages', RoomMessageController::class)
            ->readOnly();

        $server->resource('room-members', RoomMemberController::class)
            ->readOnly();
    });
