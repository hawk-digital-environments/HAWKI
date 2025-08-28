<?php

declare(strict_types=1);

use App\Orchid\Screens\Dashboard\Dashboard;
use App\Orchid\Screens\Dashboard\UserDashboard;
use App\Orchid\Screens\Dashboard\RequestsDashboard;

use App\Orchid\Screens\Settings\SystemSettingsScreen;
use App\Orchid\Screens\Settings\LogScreen;
use App\Orchid\Screens\Settings\StorageSettingsScreen;
use App\Orchid\Screens\Settings\StylingSettingsScreen;
use App\Orchid\Screens\Settings\TextsSettingsScreen;
use App\Orchid\Screens\Settings\MailSettingsScreen;

use App\Orchid\Screens\ModelSettings\ProviderSettingsScreen;
use App\Orchid\Screens\ModelSettings\ProviderCreateScreen;
use App\Orchid\Screens\ModelSettings\ProviderEditScreen;

use App\Orchid\Screens\ModelSettings\ApiFormatSettingsScreen;
use App\Orchid\Screens\ModelSettings\ApiFormatEditScreen;

use App\Orchid\Screens\ModelSettings\LanguageModelEditScreen;
use App\Orchid\Screens\ModelSettings\LanguageModelListScreen;

use App\Orchid\Screens\ModelSettings\UtilityModelsSettingsScreen;

use App\Orchid\Screens\Examples\ExampleActionsScreen;
use App\Orchid\Screens\Examples\ExampleCardsScreen;
use App\Orchid\Screens\Examples\ExampleChartsScreen;
use App\Orchid\Screens\Examples\ExampleFieldsAdvancedScreen;
use App\Orchid\Screens\Examples\ExampleFieldsScreen;
use App\Orchid\Screens\Examples\ExampleGridScreen;
use App\Orchid\Screens\Examples\ExampleLayoutsScreen;
use App\Orchid\Screens\Examples\ExampleScreen;
use App\Orchid\Screens\Examples\ExampleTextEditorsScreen;

use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/


// Main
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

// Dashboard
Route::screen('/dashboard/global', Dashboard::class)
    ->name('platform.dashboard.global')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Global Dashboard'), route('platform.dashboard.global')));
        
Route::screen('/dashboard/users', UserDashboard::class)
    ->name('platform.dashboard.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('User Dashboard'), route('platform.dashboard.users')));
        
Route::screen('/dashboard/requests', RequestsDashboard::class)
    ->name('platform.dashboard.requests')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Requests Dashboard'), route('platform.dashboard.requests')));

// Settings        
Route::screen('/settings/system', SystemSettingsScreen::class)
    ->name('platform.settings.system')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('System');
    });
Route::screen('/settings/log', LogScreen::class)
    ->name('platform.settings.log')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Log');
    });
Route::screen('/settings/storage', StorageSettingsScreen::class)
    ->name('platform.settings.storage')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Storage');
    });
Route::screen('/settings/styling', StylingSettingsScreen::class)
    ->name('platform.settings.styling')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Styling');
    });
Route::screen('/settings/texts', TextsSettingsScreen::class)
    ->name('platform.settings.texts')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Texts');
    });
Route::screen('/settings/mail', MailSettingsScreen::class)
    ->name('platform.settings.mail')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Mail');
    });

// Models - API Management - Providers
Route::screen('/models/api/providers', ProviderSettingsScreen::class)
    ->name('platform.models.api.providers')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Models'), '#')
        ->push(__('API Management'), '#')
        ->push(__('API Providers'), route('platform.models.api.providers')));
        
Route::screen('/models/api/providers/create', ProviderCreateScreen::class)
    ->name('platform.models.api.providers.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.models.api.providers')
        ->push(__('Create Provider'), route('platform.models.api.providers.create')));

Route::screen('/models/api/providers/{provider}/edit', ProviderEditScreen::class)
    ->name('platform.models.api.providers.edit')
    ->breadcrumbs(fn (Trail $trail, $provider) => $trail
        ->parent('platform.models.api.providers')
        ->push(__('Edit Provider'), route('platform.models.api.providers.edit', $provider)));

// Models - API Management - Formats
Route::screen('/models/api/formats', ApiFormatSettingsScreen::class)
    ->name('platform.models.api.formats')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Models'), '#')
        ->push(__('API Management'), '#')
        ->push(__('API Formats'), route('platform.models.api.formats')));
        
Route::screen('/models/api/formats/create', ApiFormatEditScreen::class)
    ->name('platform.models.api.formats.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.models.api.formats')
        ->push(__('Create API Format'), route('platform.models.api.formats.create')));

Route::screen('/models/api/formats/{apiFormat}/edit', ApiFormatEditScreen::class)
    ->name('platform.models.api.formats.edit')
    ->breadcrumbs(fn (Trail $trail, $apiFormat) => $trail
        ->parent('platform.models.api.formats')
        ->push(__('Edit API Format'), route('platform.models.api.formats.edit', $apiFormat)));

// Models - Language Models
Route::screen('/models/language', LanguageModelListScreen::class)
    ->name('platform.models.language')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Models'), '#')
        ->push(__('Language Models'), route('platform.models.language')));
        
Route::screen('/models/language/{model}/edit', LanguageModelEditScreen::class)
    ->name('platform.models.language.edit')
    ->breadcrumbs(fn (Trail $trail, $model) => $trail
        ->parent('platform.models.language')
        ->push(__('Edit Model'), route('platform.models.language.edit', $model)));

// Models - Utility Models  
Route::screen('/models/utility', UtilityModelsSettingsScreen::class)
    ->name('platform.models.utility')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Models'), '#')
        ->push(__('Utility Models'), route('platform.models.utility')));

    



// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

// Example...
Route::screen('example', ExampleScreen::class)
    ->name('platform.example')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Example Screen'));

Route::screen('/examples/form/fields', ExampleFieldsScreen::class)->name('platform.example.fields');
Route::screen('/examples/form/advanced', ExampleFieldsAdvancedScreen::class)->name('platform.example.advanced');
Route::screen('/examples/form/editors', ExampleTextEditorsScreen::class)->name('platform.example.editors');
Route::screen('/examples/form/actions', ExampleActionsScreen::class)->name('platform.example.actions');

Route::screen('/examples/layouts', ExampleLayoutsScreen::class)->name('platform.example.layouts');
Route::screen('/examples/grid', ExampleGridScreen::class)->name('platform.example.grid');
Route::screen('/examples/charts', ExampleChartsScreen::class)->name('platform.example.charts');
Route::screen('/examples/cards', ExampleCardsScreen::class)->name('platform.example.cards');