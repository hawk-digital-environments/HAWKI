<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin;
use Illuminate\Support\Facades\Route;

Route::get('providers', [Admin\ProviderController::class, 'index']);
Route::post('providers', [Admin\ProviderController::class, 'store']);
Route::patch('providers/{id}', [Admin\ProviderController::class, 'update'])->whereNumber('id');
Route::delete('providers/{id}', [Admin\ProviderController::class, 'destroy'])->whereNumber('id');
Route::post('providers/{id}/actions/test', [Admin\ProviderController::class, 'test'])->whereNumber('id');
Route::post('providers/{id}/actions/discover', [Admin\ProviderController::class, 'discover'])->whereNumber('id');
Route::post('providers/{id}/actions/inspect', [Admin\ProviderController::class, 'inspect'])->whereNumber('id');
Route::post('providers/actions/import', [Admin\ProviderController::class, 'import']);
Route::get('providers/actions/icons', [Admin\ProviderController::class, 'icons']);
Route::post('providers/actions/icon-upload', [Admin\ProviderController::class, 'uploadIcon']);

Route::get('models', [Admin\ModelController::class, 'index']);
Route::post('models', [Admin\ModelController::class, 'store']);
Route::patch('models/{id}', [Admin\ModelController::class, 'update'])->whereNumber('id');
Route::delete('models/{id}', [Admin\ModelController::class, 'destroy'])->whereNumber('id');
Route::post('models/{id}/actions/refresh', [Admin\ModelController::class, 'refresh'])->whereNumber('id');

Route::get('mcp', [Admin\McpServerController::class, 'index']);
Route::post('mcp', [Admin\McpServerController::class, 'store']);
Route::patch('mcp/{id}', [Admin\McpServerController::class, 'update'])->whereNumber('id');
Route::delete('mcp/{id}', [Admin\McpServerController::class, 'destroy'])->whereNumber('id');
Route::post('mcp/{id}/actions/test', [Admin\McpServerController::class, 'test'])->whereNumber('id');
Route::post('mcp/{id}/actions/discover', [Admin\McpServerController::class, 'discover'])->whereNumber('id');

Route::get('tools', [Admin\ToolController::class, 'index']);
Route::patch('tools/{id}', [Admin\ToolController::class, 'update'])->whereNumber('id');

Route::get('system-models', [Admin\SystemModelController::class, 'index']);
Route::post('system-models', [Admin\SystemModelController::class, 'store']);
Route::patch('system-models/{id}', [Admin\SystemModelController::class, 'update'])->whereNumber('id');
Route::delete('system-models/{id}', [Admin\SystemModelController::class, 'destroy'])->whereNumber('id');

Route::get('announcements', [Admin\AnnouncementController::class, 'index']);
Route::post('announcements', [Admin\AnnouncementController::class, 'store']);
Route::patch('announcements/{id}', [Admin\AnnouncementController::class, 'update'])->whereNumber('id');
Route::delete('announcements/{id}', [Admin\AnnouncementController::class, 'destroy'])->whereNumber('id');

Route::get('users', [Admin\UserController::class, 'index']);
Route::post('users', [Admin\UserController::class, 'store']);
Route::patch('users/{id}', [Admin\UserController::class, 'update'])->whereNumber('id');
Route::post('users/{id}/actions/revoke-tokens', [Admin\UserController::class, 'revokeTokens'])->whereNumber('id');
Route::get('users/{id}/actions/tokens', [Admin\UserController::class, 'tokens'])->whereNumber('id');

Route::get('roles', [Admin\RoleController::class, 'index']);
Route::post('roles', [Admin\RoleController::class, 'store']);
Route::patch('roles/{id}', [Admin\RoleController::class, 'update'])->whereNumber('id');
Route::delete('roles/{id}', [Admin\RoleController::class, 'destroy'])->whereNumber('id');

Route::get('mappings', [Admin\RoleMappingController::class, 'index']);
Route::post('mappings', [Admin\RoleMappingController::class, 'store']);
Route::patch('mappings/{id}', [Admin\RoleMappingController::class, 'update'])->whereNumber('id');
Route::delete('mappings/{id}', [Admin\RoleMappingController::class, 'destroy'])->whereNumber('id');

Route::get('settings', [Admin\SettingController::class, 'index']);
Route::patch('settings/{id}', [Admin\SettingController::class, 'update']);
Route::delete('settings/{id}', [Admin\SettingController::class, 'destroy']);

Route::get('usage', [Admin\UsageController::class, 'index']);

Route::get('environment', [Admin\EnvironmentController::class, 'index']);

Route::get('health', [Admin\HealthController::class, 'index']);
Route::post('health/actions/check-ai-status', [Admin\HealthController::class, 'checkAiStatus']);
Route::post('health/{id}/actions/retry-job', [Admin\HealthController::class, 'retryJob']);
Route::post('health/actions/flush-jobs', [Admin\HealthController::class, 'flushJobs']);
