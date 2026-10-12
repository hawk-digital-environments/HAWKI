<?php

declare(strict_types=1);

namespace App\Services\Assistant\Routes;

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
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Routing\ActionRegistrar;
use LaravelJsonApi\Laravel\Routing\Relationships;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;

/**
 * Registers the Assistant slice's API surface: the JSON:API resources and
 * their relationship/action routes, plus the client-schema route the
 * assistants frontend plugin bootstraps from.
 *
 * Routes live with their slice so the future plugin package ships them via
 * its own route file (plugin system §1.5) — that file calls this registrar
 * unchanged.
 */
final class AssistantApiRoutes
{
    /**
     * The client-schema endpoint the assistants frontend plugin bootstraps
     * from. Called from the authenticated API-prefix group in
     * `routes/api.php`.
     */
    public static function plainRoutes(): void
    {
        Route::get('/assistants/schema', ClientSchemaController::class);
    }

    /**
     * The slice's JSON:API resources. Called from inside the server's
     * authenticated resources closure in `routes/api.php`.
     */
    public static function jsonApiResources(ResourceRegistrar $server): void
    {
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
                $relationships->hasMany('assistant_attachments')->readOnly();
            })
            ->actions(static function (ActionRegistrar $actions): void {
                $actions->withId()->post('actions/remix', 'remix');
                $actions->withId()->post('actions/release', 'release');
                $actions->withId()->post('actions/favorite', 'addFavorite');
                $actions->withId()->delete('actions/favorite', 'removeFavorite');
                $actions->withId()->post('actions/attachment', 'uploadAttachment');
                $actions->withId()->delete('actions/attachment', 'deleteAttachment');
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

        // assistant-user-prompts and assistant-feedback are write-only resources: the
        // `assistant` relationship is set on create, but neither exposes relationship
        // read endpoints, so no ->relationships() block is registered.
        $server->resource('assistant-user-prompts', AssistantUserPromptController::class)
            ->only('store', 'destroy');

        $server->resource('assistant-feedback', AssistantFeedbackController::class)
            ->only('store');
    }
}
