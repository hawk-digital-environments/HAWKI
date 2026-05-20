<?php

use App\Http\Controllers\AiModelController;
use App\Http\Controllers\AiToolController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AssistantLanguageController;
use App\Http\Controllers\McpServerController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Routing\ActionRegistrar;

use App\Models\User;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['api_isActive', 'auth:sanctum'])->group(function () {

    Route::post('ai-req', [StreamController::class, 'handleExternalRequest']);

});

Route::middleware(['auth:sanctum'])->group(function () {
    JsonApiRoute::server('v1')
        ->prefix('')
        ->resources(function ($server) {
            $server->resource('assistants', AssistantController::class)
                ->relationships(function ($relationships) {
                    $relationships->hasOne('language')->readOnly();
                    $relationships->hasOne('category')->readOnly();
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
                });

            $server->resource('assistant-categories', CategoryController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('assistants')->readOnly();
                });

            $server->resource('assistant-languages', AssistantLanguageController::class)
                ->only('index', 'show')
                ->relationships(function ($relationships) {
                    $relationships->hasMany('assistants')->readOnly();
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
                });
        });
});
