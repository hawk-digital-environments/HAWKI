<?php

use App\Http\Controllers\AiModelController;
use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\AiToolController;
use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\ClientSchemaController;
use App\Http\Controllers\Assistant\AssistantAvatarController;
use App\Http\Controllers\Assistant\AssistantSettingController;
use App\Http\Controllers\Assistant\AssistantSettingValueController;
use App\Http\Controllers\Assistant\CategoryController;
use App\Http\Controllers\McpServerController;
use App\Http\Controllers\Assistant\ReviewController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\Assistant\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Routing\ActionRegistrar;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

});
Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('assistants/schema', ClientSchemaController::class);

    Route::post('assistants/{assistantId}/actions/chat-test{tail?}', [AssistantController::class, 'chatTest'])
        ->where('tail', '/.*');

    JsonApiRoute::server('v1')
        ->prefix('')
        ->resources(function ($server) {
            $server->resource('assistants', AssistantController::class)
                ->relationships(function ($relationships) {
                    $relationships->hasOne('category')->readOnly();
                    $relationships->hasMany('setting_values')->readonly();
                    $relationships->hasMany('user_prompts')->readOnly();
                    $relationships->hasMany('ai_tools')->readOnly();
                    $relationships->hasMany('tags')->readOnly();
                    $relationships->hasOne('creator')->readOnly();
                    $relationships->hasOne('remix_creator')->readOnly();
                    $relationships->hasOne('remixed_assistant')->readOnly();
                    $relationships->hasMany('versions')->readOnly();
                    $relationships->hasOne('organization')->readOnly();
                    $relationships->hasOne('review')->readOnly();
                })
                ->actions('actions', function (ActionRegistrar $actions) {
                    $actions->withId()->post('remix');
                    $actions->withId()->post('release');
                    $actions->withId()->post('feedback');
                    $actions->withId()->post('favorite');
                    $actions->withId()->post('settings');
                });

            $server->resource('assistant-avatars', AssistantAvatarController::class)
                ->only('index', 'show');

            $server->resource('assistant-categories', CategoryController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('assistants')->readOnly();
                });

            $server->resource('tags', TagController::class)
                ->only('index', 'show', 'store', 'destroy')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('assistants')->readOnly();
                });

            $server->resource('assistant-settings', AssistantSettingController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('values')->readOnly();
                });

            $server->resource('assistant-setting-values', AssistantSettingValueController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasOne('assistant')->readOnly();
                    $relationships->hasOne('setting')->readOnly();
                });

            $server->resource('assistant-reviews', ReviewController::class)
                ->only('index', 'show', 'update')
                ->relationships(function ($relationships) {
                    $relationships->hasOne('assistant')->readOnly();
                });

            $server->resource('ai-tools', AiToolController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasOne('server')->readOnly();
                    $relationships->hasMany('models')->readOnly();
                });

            $server->resource('mcp-servers', McpServerController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('tools')->readOnly();
                });

            $server->resource('ai-models', AiModelController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasOne('provider')->readOnly();
                    $relationships->hasMany('assignedTools')->readOnly();
                    $relationships->hasOne('status')->readOnly();
                });

            $server->resource('ai-providers', AiProviderController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('models')->readOnly();
                });
        });
});
