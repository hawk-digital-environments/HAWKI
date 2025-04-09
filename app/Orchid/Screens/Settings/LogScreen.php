<?php

namespace App\Orchid\Screens\Settings;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Support\Facades\Toast;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\Group;


use Illuminate\Support\Facades\Log;
use Orchid\Support\Color;
use Orchid\Screen\Fields\Code;

use Orchid\Support\Facades\Alert;

class LogScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Filterwerte aus Session lesen, Standard: false
        $localInfo = session()->get('localInfo', false);
        $localError = session()->get('localError', false);

        $log = file_exists(storage_path('logs/laravel.log'))
            ? file_get_contents(storage_path('logs/laravel.log'))
            : 'Log-Datei nicht gefunden.';

        // Log zeilenweise filtern
        $lines = explode(PHP_EOL, $log);
        $filteredLines = [];
        foreach ($lines as $line) {
            if (!$localInfo && strpos($line, 'local.INFO') !== false) {
                continue;
            }
            if (!$localError && strpos($line, 'local.ERROR') !== false) {
                continue;
            }
            $filteredLines[] = $line;
        }
        $log = implode(PHP_EOL, $filteredLines);
        
        return [
            'logs'       => $log,
            'localInfo'  => $localInfo,
            'localError' => $localError,
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
            Layout::rows([
                Group::make([
                    Switcher::make('localInfo')
                        ->placeholder('local.INFO')
                        ->sendTrueOrFalse(),
                    Switcher::make('localError')
                        ->placeholder('local.ERROR')
                        ->sendTrueOrFalse(),
                    Button::make('Save Filters')
                        ->icon('check')
                        ->method('updateSettings')
                        ->async()
                        ->type(Color::BASIC),
                ])
                ->widthColumns('max-content 1fr max-content'),
            ]),

            Layout::rows([
                Code::make('logs')
                    ->title('Laravel Log')
                    ->readonly(true)
                    ->height("70dvh"),
            ]),
        ];
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