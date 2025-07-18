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
                ->route(config('platform.index')),
            
            Menu::make('Dashboard')
                ->icon('bs.rocket-takeoff')
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

            Menu::make('System')
                ->title('Configuration')
                ->icon('bs.house-gear')
                ->badge(fn () => $this->getHawkiCommitId(), Color::DARK)
                ->list([
                    Menu::make('Settings')
                        ->route('platform.settings.system')
                        ->icon('bs.gear'),
                    Menu::make('Log')
                        ->route('platform.settings.log')
                        ->icon('bs.journal-code'),    
                    Menu::make('Storage')
                        ->route('platform.settings.storage')
                        ->icon('bs.database'),     
                    Menu::make('Styling')
                        ->route('platform.settings.styling')
                        ->icon('bs.paint-bucket'),       
                    Menu::make('Texts')
                        ->route('platform.settings.texts')
                        ->icon('bs.info-circle'),    
                    ]),    
            
            Menu::make('Models')
                ->icon('bs.stars')
                ->list([        
                    Menu::make('API Providers')
                        ->route('platform.modelsettings.providers')
                        ->icon('bs.plug'),
                    Menu::make('Model Settings')
                        ->route('platform.modelsettings.models')
                        ->icon('bs.toggles'),
                    Menu::make('Utility Models')
                        ->route('platform.modelsettings.utilitymodels')
                        ->icon('bs.tools'),                  
                    ]),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Access Controls')),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles')
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
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
