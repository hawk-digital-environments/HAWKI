<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Get Started')
                ->icon('bs.book')
                ->title('Overview')
                ->permission('platform.systems.*')
                ->route(config('platform.index')),
            
            Menu::make('Dashboard')
                ->icon('bs.rocket-takeoff')
                ->permission('platform.dashboard')
                ->list([
                    Menu::make('Global')
                        ->route('platform.dashboard.global')
                        ->icon('bs.globe2'),
                    Menu::make('Users')
                        ->route('platform.dashboard.users')
                        ->icon('bs.people'),
                    Menu::make('Requests')
                        ->route('platform.dashboard.requests')
                        ->icon('bs.bar-chart'),
                    ]),
            
            Menu::make('')
                ->title(__('Configuration'))
                ->permission('platform.systems.*'),

            Menu::make('System')
                ->icon('bs.house-gear')
                ->permission('platform.systems.settings')
                ->badge(fn () => $this->getHawkiCommitId(), Color::DARK)
                ->active('platform.settings.*')
                ->list([
                    Menu::make('Settings')
                        ->route('platform.settings.system')
                        ->icon('bs.gear')
                        ->active('platform.settings.system*'),
                    Menu::make('Log Management')
                        ->route('platform.settings.log.system')
                        ->icon('bs.journal-code')
                        ->active('platform.settings.log*'),    
                    //Menu::make('Storage')
                    //    ->route('platform.settings.storage')
                    //    ->icon('bs.database')
                    //    ->active('platform.settings.storage*'),     
                    Menu::make('Styling')
                        ->route('platform.settings.styling')
                        ->icon('bs.paint-bucket')
                        ->active('platform.settings.styling*'),       
                    Menu::make('Texts')
                        ->route('platform.settings.texts')
                        ->icon('bs.info-circle')
                        ->active('platform.settings.texts*'),
                    Menu::make('Mail')
                        ->route('platform.settings.mail')
                        ->icon('bs.envelope')
                        ->active('platform.settings.mail*'),
                    Menu::make('WebSockets')
                        ->route('platform.settings.websockets')
                        ->icon('bs.wifi')
                        ->active('platform.settings.websockets*'),
                    ]),    
            
            Menu::make('Models')
                ->icon('bs.stars')
                ->permission('platform.systems.models')
                ->active('platform.models.*')
                ->list([        
                    Menu::make('API Management')
                        ->route('platform.models.api.providers')
                        ->permission('platform.modelsettings.providers')
                        ->icon('bs.cloud-upload')
                        ->active('platform.models.api*'),
                    Menu::make('Language Models')
                        ->route('platform.models.language')
                        ->permission('platform.modelsettings.models')
                        ->icon('bs.toggles'),
                    Menu::make('Utility Models')
                        ->route('platform.models.utility')
                        ->permission('platform.modelsettings.utilitymodels')
                        ->icon('bs.tools'),                  
                    ]),

            Menu::make('')
                ->title(__('Access Controls'))
                ->permission('platform.access.*'),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.access.users'),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.access.roles'),

            Menu::make('Role Assignments')
                ->icon('bs.diagram-3')
                ->route('platform.role-assignments')
                ->permission('platform.role-assignments'),
            
            Menu::make('')
                ->divider(),

            Menu::make('Documentation')
                ->title('Docs')
                ->icon('bs.box-arrow-up-right')
                ->url('https://orchid.software/en/docs')
                ->target('_blank'),

            Menu::make('Changelog')
                ->icon('bs.box-arrow-up-right')
                ->url('https://github.com/orchidsoftware/platform/blob/master/CHANGELOG.md')
                ->target('_blank')
                ->badge(fn () => Dashboard::version(), Color::DARK),
        ];
    }

    /**
     * Get the hawki submodule commit ID from git_info.json
     *
     * @return string
     */
    private function getHawkiCommitId(): string
    {
        try {
            $gitInfoPath = storage_path('app/git_info.json');
            
            if (!file_exists($gitInfoPath)) {
                return 'N/A';
            }
            
            $gitInfo = json_decode(file_get_contents($gitInfoPath), true);
            
            if (!isset($gitInfo['hawki_submodule']['commit_id'])) {
                return 'N/A';
            }
            
            return $gitInfo['hawki_submodule']['commit_id'];
        } catch (\Exception $e) {
            return 'N/A';
        }
    }


    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('Main'))
                ->addPermission('platform.systems.settings', __('System Settings'))
                ->addPermission('platform.settings.log', __('Log Management'))
                ->addPermission('platform.systems.models', __('Model Settings')),
                
            ItemPermission::group(__('Model Settings'))
                ->addPermission('platform.modelsettings.providers', __('API Providers'))
                ->addPermission('systems.modelsettings', __('API Formats'))
                ->addPermission('platform.modelsettings.models', __('Language Models'))
                ->addPermission('platform.modelsettings.utilitymodels', __('Utility Models')),
                
            ItemPermission::group(__('Access Controls'))
                ->addPermission('platform.access.roles', __('Roles'))
                ->addPermission('platform.access.users', __('Users'))
                ->addPermission('platform.role-assignments', __('Role Assignments')),
                
            ItemPermission::group(__('Reporting'))
                ->addPermission('platform.dashboard', __('Dashboard')),
                
            ItemPermission::group(__('Chat Access'))
                ->addPermission('chat.access', __('Chat Access'))
                ->addPermission('groupchat.access', __('Group Chat Access')),
        ];
    }
}
