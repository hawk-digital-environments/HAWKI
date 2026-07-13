<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AiCapabilityController;
use App\Http\Controllers\Api\V1\OpenaiResponsesController;
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
use App\Http\Controllers\Assistant\AssistantAvatarController;
use App\Http\Controllers\Assistant\AssistantCategoryController;
use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\Assistant\AssistantFeedbackController;
use App\Http\Controllers\Assistant\AssistantReviewController;
use App\Http\Controllers\Assistant\AssistantSettingController;
use App\Http\Controllers\Assistant\AssistantSettingValueController;
use App\Http\Controllers\Assistant\AssistantTagController;
use App\Http\Controllers\Assistant\AssistantUserPromptController;
use App\Http\Controllers\ClientSchemaController;
use App\Http\Controllers\LinkPreviewController;
use App\Http\Controllers\OpenApiSpecController;
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

Route::group(['prefix' => Server::BASE_URL_PREFIX], static function (): void {
    Route::get('/openapi.json', OpenApiSpecController::class);
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

        Route::get('/assistants/schema', ClientSchemaController::class);
    });

    Route::prefix('openai/v1')->group(static function (): void {
        Route::post('/responses', OpenaiResponsesController::class)
            ->name('api.openai.responses');
    });
});

JsonApiRoute::server('v1')
    ->prefix(Server::BASE_URL_PREFIX)
    ->middleware(
        BlockExtAppsIfNotAllowedMiddleware::class,
        AppTokenForbiddenMiddleware::class,
        ApiDataScopeContextSettingMiddleware::class,
    )
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
            ->readOnly()
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->get('me', 'handleMe')
                    ->withoutMiddleware(AppTokenForbiddenMiddleware::class);
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

        $server->resource('room-messages', RoomMessageController::class)
            ->readOnly();

        $server->resource('room-members', RoomMemberController::class)
            ->readOnly();

        $server->resource('assistants', AssistantController::class)
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant_category')->readOnly();
                $relationships->hasOne('assistant_avatar')->readOnly();
                $relationships->hasMany('assistant_setting_values')->readOnly();
                $relationships->hasMany('assistant_user_prompts')->readOnly();
                $relationships->hasMany('ai_tools');
                $relationships->hasOne('creator')->readOnly();
                $relationships->hasOne('remix_creator')->readOnly();
                $relationships->hasOne('remixed_assistant')->readOnly();
                $relationships->hasMany('assistant_versions')->readOnly();
                $relationships->hasOne('organization')->readOnly();
                $relationships->hasOne('assistant_review')->readOnly();
                $relationships->hasMany('assistant_tags');
                $relationships->hasMany('assistant_feedback')->readOnly();
                $relationships->hasMany('shared_users');
            })
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->withId()->post('actions/remix', 'remix');
                $actions->withId()->post('actions/release', 'release');
                $actions->withId()->post('actions/favorite', 'addFavorite');
                $actions->withId()->delete('actions/favorite', 'removeFavorite');
            });

        $server->resource('assistant-avatars', AssistantAvatarController::class)
            ->only('index', 'show', 'store', 'update', 'destroy')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant')->readOnly();
            });

        $server->resource('assistant-categories', AssistantCategoryController::class)
            ->only('index', 'show')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasMany('assistants')->readOnly();
            });

        $server->resource('assistant-tags', AssistantTagController::class)
            ->only('index', 'show', 'store');

        $server->resource('assistant-reviews', AssistantReviewController::class)
            ->only('index', 'update')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant')->readOnly();
            });

        $server->resource('assistant-settings', AssistantSettingController::class)
            ->only('index', 'show')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasMany('values')->readOnly();
            });

        $server->resource('assistant-setting-values', AssistantSettingValueController::class)
            ->only('index', 'show', 'store', 'update', 'destroy')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant')->readOnly();
                $relationships->hasOne('setting')->readOnly();
            });

        $server->resource('assistant-user-prompts', AssistantUserPromptController::class)
            ->only('store', 'destroy')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant')->readOnly();
            });

        $server->resource('assistant-feedback', AssistantFeedbackController::class)
            ->only('store')
            ->relationships(static function (Relationships $relationships): void {
                $relationships->hasOne('assistant')->readOnly();
                $relationships->hasOne('user')->readOnly();
            });
    });
