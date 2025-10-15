<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Orchid\Screens\Customization\CssEditScreen;
use App\Orchid\Screens\Customization\CssRulesScreen;
use App\Orchid\Screens\Customization\AnnouncementScreen;
use App\Orchid\Screens\Customization\AnnouncementEditScreen;
use App\Orchid\Screens\Customization\LocalizedTextScreen;
use App\Orchid\Screens\Customization\MailTemplateEditScreen;
use App\Orchid\Screens\Customization\MailTemplatesScreen;
use App\Orchid\Screens\Customization\SystemImageEditScreen;
use App\Orchid\Screens\Customization\SystemImagesScreen;
use App\Orchid\Screens\Customization\SystemTextScreen;
use App\Orchid\Screens\Dashboard\Dashboard;
use App\Orchid\Screens\Dashboard\RequestsDashboard;
use App\Orchid\Screens\Dashboard\UserDashboard;
use App\Orchid\Screens\Examples\ExampleActionsScreen;
use App\Orchid\Screens\Examples\ExampleCardsScreen;
use App\Orchid\Screens\Examples\ExampleChartsScreen;
use App\Orchid\Screens\Examples\ExampleFieldsAdvancedScreen;
use App\Orchid\Screens\Examples\ExampleFieldsScreen;
use App\Orchid\Screens\Examples\ExampleGridScreen;
use App\Orchid\Screens\Examples\ExampleLayoutsScreen;
use App\Orchid\Screens\Examples\ExampleScreen;
use App\Orchid\Screens\Examples\ExampleTextEditorsScreen;
use App\Orchid\Screens\ModelSettings\ApiFormatEditScreen;
use App\Orchid\Screens\ModelSettings\ApiFormatSettingsScreen;
use App\Orchid\Screens\ModelSettings\AiModelEditScreen;
use App\Orchid\Screens\ModelSettings\AiModelListScreen;
use App\Orchid\Screens\ModelSettings\ProviderCreateScreen;
use App\Orchid\Screens\ModelSettings\ProviderEditScreen;
use App\Orchid\Screens\ModelSettings\ApiProvidersScreen;
use App\Orchid\Screens\ModelSettings\AssistantsScreen;
use App\Orchid\Screens\ModelSettings\AssistantEditScreen;
use App\Orchid\Screens\ModelSettings\PromptsScreen;
use App\Orchid\Screens\ModelSettings\PromptEditScreen;
use App\Orchid\Screens\ModelSettings\ToolsScreen;
use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleAssignmentEditScreen;
use App\Orchid\Screens\Role\RoleAssignmentScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\Settings\ApiSettingsScreen;
use App\Orchid\Screens\Settings\AuthenticationSettingsScreen;
use App\Orchid\Screens\Settings\AuthMethodEditScreen;
use App\Orchid\Screens\Settings\LogScreen;
use App\Orchid\Screens\Settings\MailConfigurationSettingsScreen;
use App\Orchid\Screens\Settings\StorageSettingsScreen;
use App\Orchid\Screens\Settings\SystemSettingsScreen;
use App\Orchid\Screens\Settings\WebSocketSettingsScreen;
use App\Orchid\Screens\Testing\MailTestingScreen;
use App\Orchid\Screens\Testing\TestingSettingsScreen;
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
            ->push('System Settings', route('platform.settings.system'));
    });

Route::screen('/settings/authentication', AuthenticationSettingsScreen::class)
    ->name('platform.settings.authentication')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Authentication Settings', route('platform.settings.authentication'));
    });

Route::screen('/settings/authentication/edit', AuthMethodEditScreen::class)
    ->name('platform.settings.authentication.edit')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.settings.authentication')
            ->push('Edit Authentication Method', route('platform.settings.authentication.edit'));
    });

Route::screen('/settings/api', ApiSettingsScreen::class)
    ->name('platform.settings.api')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('API Settings', route('platform.settings.api'));
    });

Route::screen('/settings/mail-configuration', MailConfigurationSettingsScreen::class)
    ->name('platform.settings.mail-configuration')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Mail Configuration', route('platform.settings.mail-configuration'));
    });

// Settings - Log Management
Route::redirect('/settings/log', '/settings/log/system');

Route::screen('/settings/log/system', LogScreen::class)
    ->name('platform.settings.log.system')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('System Log', route('platform.settings.log.system'));
    });

Route::screen('/settings/log/configuration', LogScreen::class)
    ->name('platform.settings.log.configuration')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Log Configuration', route('platform.settings.log.configuration'));
    });
Route::screen('/settings/storage', StorageSettingsScreen::class)
    ->name('platform.settings.storage')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Settings', '#')
            ->push('Storage', route('platform.settings.storage'));
    });
// Customization Routes
Route::screen('/customization/images', SystemImagesScreen::class)
    ->name('platform.customization.images')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Images', route('platform.customization.images'));
    });

// System Image Edit Routes
Route::screen('/customization/systemimages', SystemImagesScreen::class)
    ->name('platform.customization.systemimages')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('System Images', route('platform.customization.systemimages'));
    });

Route::screen('/customization/systemimages/create', SystemImageEditScreen::class)
    ->name('platform.customization.systemimages.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.systemimages')
            ->push('Create System Image', route('platform.customization.systemimages.create'));
    });

Route::screen('/customization/systemimages/{image_name}/edit', SystemImageEditScreen::class)
    ->name('platform.customization.systemimages.edit')
    ->breadcrumbs(function (Trail $trail, $image_name) {
        return $trail
            ->parent('platform.customization.systemimages')
            ->push('Edit: '.$image_name, route('platform.customization.systemimages.edit', $image_name));
    });
Route::screen('/customization/css', CssRulesScreen::class)
    ->name('platform.customization.css')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('CSS', route('platform.customization.css'));
    });

Route::screen('/customization/css/create', CssEditScreen::class)
    ->name('platform.customization.css.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.css')
            ->push('Create CSS Rule', route('platform.customization.css.create'));
    });

Route::screen('/customization/css/{css_name}/edit', CssEditScreen::class)
    ->name('platform.customization.css.edit')
    ->breadcrumbs(function (Trail $trail, $css_name) {
        return $trail
            ->parent('platform.customization.css')
            ->push('Edit: '.$css_name, route('platform.customization.css.edit', $css_name));
    });
Route::screen('/customization/localized-text', LocalizedTextScreen::class)
    ->name('platform.customization.localizedtexts')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Localized Text', route('platform.customization.localizedtexts'));
    });

Route::screen('/customization/localizedtexts/create', \App\Orchid\Screens\Customization\LocalizedTextEditScreen::class)
    ->name('platform.customization.localizedtexts.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.localizedtexts')
            ->push('Create Localized Text', route('platform.customization.localizedtexts.create'));
    });

Route::screen('/customization/localizedtexts/{content_key}/edit', \App\Orchid\Screens\Customization\LocalizedTextEditScreen::class)
    ->name('platform.customization.localizedtexts.edit')
    ->where('content_key', '.*')
    ->breadcrumbs(function (Trail $trail, $content_key) {
        return $trail
            ->parent('platform.customization.localizedtexts')
            ->push('Edit: '.$content_key, route('platform.customization.localizedtexts.edit', $content_key));
    });
Route::screen('/customization/systemtexts', SystemTextScreen::class)
    ->name('platform.customization.systemtexts')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('System Texts', route('platform.customization.systemtexts'));
    });

Route::screen('/customization/systemtexts/create', \App\Orchid\Screens\Customization\SystemTextEditScreen::class)
    ->name('platform.customization.systemtexts.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.systemtexts')
            ->push('Create System Text', route('platform.customization.systemtexts.create'));
    });

Route::screen('/customization/systemtexts/{systemText}/edit', \App\Orchid\Screens\Customization\SystemTextEditScreen::class)
    ->name('platform.customization.systemtexts.edit')
    ->breadcrumbs(function (Trail $trail, $systemText) {
        return $trail
            ->parent('platform.customization.systemtexts')
            ->push('Edit: '.$systemText, route('platform.customization.systemtexts.edit', $systemText));
    });

// Mail Templates
Route::screen('/customization/mailtemplates', MailTemplatesScreen::class)
    ->name('platform.customization.mailtemplates')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Mail Templates', route('platform.customization.mailtemplates'));
    });

Route::screen('/customization/mailtemplates/create', MailTemplateEditScreen::class)
    ->name('platform.customization.mailtemplates.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.mailtemplates')
            ->push('Create Mail Template', route('platform.customization.mailtemplates.create'));
    });

Route::screen('/customization/mailtemplates/{template_type}/edit', MailTemplateEditScreen::class)
    ->name('platform.customization.mailtemplates.edit')
    ->where('template_type', '.*')
    ->breadcrumbs(function (Trail $trail, $template_type) {
        return $trail
            ->parent('platform.customization.mailtemplates')
            ->push('Edit: '.$template_type, route('platform.customization.mailtemplates.edit', $template_type));
    });

Route::screen('/customization/mail-templates', MailTemplatesScreen::class)
    ->name('platform.customization.mail-templates')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Mail Templates', route('platform.customization.mail-templates'));
    });

// Announcements
Route::screen('/customization/announcements', AnnouncementScreen::class)
    ->name('platform.customization.announcements')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Announcements', route('platform.customization.announcements'));
    });

Route::screen('/customization/announcements/create', AnnouncementEditScreen::class)
    ->name('platform.customization.announcements.create')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.customization.announcements')
            ->push('Create Announcement', route('platform.customization.announcements.create'));
    });

Route::screen('/customization/announcements/{announcement}/edit', AnnouncementEditScreen::class)
    ->name('platform.customization.announcements.edit')
    ->breadcrumbs(function (Trail $trail, $announcement) {
        return $trail
            ->parent('platform.customization.announcements')
            ->push('Edit: ' . $announcement->title, route('platform.customization.announcements.edit', $announcement));
    });

Route::screen('/settings/websockets', WebSocketSettingsScreen::class)
    ->name('platform.settings.websockets')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('WebSocket Settings', route('platform.settings.websockets'));
    });

// Models - API Management - Providers
Route::screen('/models/api/providers', ApiProvidersScreen::class)
    ->name('platform.models.api.providers')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
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
Route::screen('/models/language', AiModelListScreen::class)
    ->name('platform.models.language')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Language Models'), route('platform.models.language')));

Route::screen('/models/language/{model}/edit', AiModelEditScreen::class)
    ->name('platform.models.language.edit')
    ->breadcrumbs(fn (Trail $trail, $model) => $trail
        ->parent('platform.models.language')
        ->push(__('Edit Model'), route('platform.models.language.edit', $model)));

// Models - Assistants
Route::screen('/models/assistants', AssistantsScreen::class)
    ->name('platform.models.assistants')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Assistants'), route('platform.models.assistants')));

Route::screen('/models/assistants/create', AssistantEditScreen::class)
    ->name('platform.models.assistants.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.models.assistants')
        ->push(__('Create Assistant'), route('platform.models.assistants.create')));

Route::screen('/models/assistants/{assistant}/edit', AssistantEditScreen::class)
    ->name('platform.models.assistants.edit')
    ->breadcrumbs(fn (Trail $trail, $assistant) => $trail
        ->parent('platform.models.assistants')
        ->push($assistant->name, route('platform.models.assistants.edit', $assistant)));

// Models - Prompts
Route::screen('/models/prompts', PromptsScreen::class)
    ->name('platform.models.prompts')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Prompts'), route('platform.models.prompts')));

Route::screen('/models/prompts/create', PromptEditScreen::class)
    ->name('platform.models.prompts.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.models.prompts')
        ->push(__('Create Prompt'), route('platform.models.prompts.create')));

Route::screen('/models/prompts/{prompt}/edit', PromptEditScreen::class)
    ->name('platform.models.prompts.edit')
    ->breadcrumbs(fn (Trail $trail, $prompt) => $trail
        ->parent('platform.models.prompts')
        ->push($prompt->title, route('platform.models.prompts.edit', $prompt)));

// Models - Tools
Route::screen('/models/tools', ToolsScreen::class)
    ->name('platform.models.tools')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Tools'), route('platform.models.tools')));

// Models - AI Management (Redirect to Assistants)
Route::get('/models/ai-management', function () {
    return redirect()->route('platform.models.assistants');
})->name('platform.models.ai-management');

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

// Platform > System > Role Assignments
Route::screen('role-assignments', RoleAssignmentScreen::class)
    ->name('platform.role-assignments')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Role Assignments'), route('platform.role-assignments')));

// Platform > System > Role Assignments > Edit
Route::screen('role-assignments/{employeetype}/edit', RoleAssignmentEditScreen::class)
    ->name('platform.role-assignments.edit')
    ->breadcrumbs(fn (Trail $trail, $employeetype) => $trail
        ->parent('platform.role-assignments')
        ->push($employeetype->display_name, route('platform.role-assignments.edit', $employeetype)));

// Platform > System > Role Assignments > Create
Route::screen('role-assignments/create', RoleAssignmentEditScreen::class)
    ->name('platform.role-assignments.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.role-assignments')
        ->push(__('Create Mapping'), route('platform.role-assignments.create')));

// API Routes for Role Assignment Management
Route::post('role-assignments/make-primary/{assignment}', [RoleAssignmentController::class, 'makePrimary'])
    ->name('platform.role-assignments.make-primary');

Route::delete('role-assignments/remove-assignment/{assignment}', [RoleAssignmentController::class, 'removeAssignment'])
    ->name('platform.role-assignments.remove-assignment');

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

// Testing
Route::screen('/testing/settings', TestingSettingsScreen::class)
    ->name('platform.testing.settings')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Testing', '#')
            ->push('Testing Settings', route('platform.testing.settings'));
    });

Route::screen('/testing/mail', MailTestingScreen::class)
    ->name('platform.testing.mail')
    ->breadcrumbs(function (Trail $trail) {
        return $trail
            ->parent('platform.index')
            ->push('Testing', '#')
            ->push('Mail Testing', route('platform.testing.mail'));
    });
