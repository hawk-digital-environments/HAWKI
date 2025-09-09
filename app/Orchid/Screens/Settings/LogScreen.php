<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use App\Orchid\Traits\OrchidSettingsManagementTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Support\Facades\Toast;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;

use Illuminate\Support\Facades\Log;
use Orchid\Support\Color;
use Orchid\Screen\Fields\Code;

use Orchid\Support\Facades\Alert;

class LogScreen extends Screen
{
    use OrchidSettingsManagementTrait;
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Construct the screen
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $log = file_exists(storage_path('logs/laravel.log'))
            ? file_get_contents(storage_path('logs/laravel.log'))
            : 'Log-Datei nicht gefunden.';
        
        // Fetch logging settings from database - search for the correct key format
        $loggingSettings = AppSetting::where('key', 'LIKE', 'logging_%')->get();
        
        return [
            'logs' => $log,
            'logging_settings' => $loggingSettings,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Laravel Log';
    }

    public function description(): ?string
    {
        return 'Configure what gets logged to the Laravel log file.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Clear Log')
                ->icon('trash')
                ->method('clearLog'),
            Button::make('Test Log')
                ->icon('umbrella')
                ->method('testLog'),  
            Button::make('Refresh Log')
                ->icon('arrow-clockwise')
                ->method('refreshLog'),
            Button::make('Save Settings')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    /**
     * Clear the log file.
     */
    public function clearLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            Toast::success('Log cleared.');
        } else {
            Toast::error('Log file not found.');
        }
    }

    public function testLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            //file_put_contents($logFile, PHP_EOL . 'Testing!' . PHP_EOL, FILE_APPEND);
        } else {
            Toast::error('Log file not found.');
        }
        Log::info("message");
        Log::error("message");
    }

    public function refreshLog()
    {
    
    }

    public function buttonClickProcessing(): void
    {
        Toast::warning('Click Processing');
        Log::info("message");
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            $this->buildLoggingSettingsLayout(),
            
            Layout::rows([
                Code::make('logs')
                    ->title('Laravel Log')
                    ->readonly(true)
                    ->height("70dvh"),
            ]),
        ];
    }

    /**
     * Build layout for logging settings
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildLoggingSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['logging_settings'] as $setting) {
            $fields[] = $this->generateFieldForSetting($setting);
        }

        return Layout::rows($fields)->title('Log Triggers');
    }

    /**
     * Updates the settings.
     */
    public function updateSettings()
    {
        $localInfo  = request('localInfo');
        $localError = request('localError');
        
        // Save filters in Session:
        session()->put('localInfo', $localInfo);
        session()->put('localError', $localError);
        
        Toast::info('Filter settings updated.');
    }
}