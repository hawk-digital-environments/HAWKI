<?php

namespace App\Orchid\Screens\Testing;

use App\Http\Controllers\TestConfigValueController;
use App\Orchid\Layouts\Testing\TestingTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TestingSettingsScreen extends Screen
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
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Fetch test configuration data
        $configData = [];
        $rawResponse = '';
        try {
            // Direct invocation of the controller instead of an HTTP request
            $controller = new TestConfigValueController;
            $response = $controller->__invoke();
            $responseData = $response->getData(true);
            $rawResponse = json_encode($responseData, JSON_PRETTY_PRINT);

            // Extract config_values
            $configValues = $responseData['config_values'] ?? [];

            // Prepare data for table display
            foreach ($configValues as $key => $values) {
                $configData[] = [
                    'key' => $key,
                    'config_value' => $values['config_value'] ?? 'N/A',
                    'env_value' => $values['env_value'] ?? 'N/A',
                    'db_value' => $values['db_value'] ?? 'N/A',
                    'config_source' => $values['config_source'] ?? 'N/A',
                ];
            }
        } catch (\Exception $e) {
            $configData[] = [
                'key' => 'Error',
                'config_value' => 'Failed to retrieve configuration data: '.$e->getMessage(),
                'env_value' => 'N/A',
                'db_value' => 'N/A',
                'config_source' => 'N/A',
            ];
            $rawResponse = 'Error fetching data: '.$e->getMessage();
        }

        return [
            'config_data' => $configData,
            'raw_response' => $rawResponse,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Testing & Diagnostics';
    }

    public function description(): ?string
    {
        return 'System diagnostics, configuration testing, and maintenance tools.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Run Settings Seeder')
                ->icon('bs.arrow-clockwise')
                ->confirm('This will import/update settings from configuration files. Are you sure?')
                ->method('runSettingsSeeder'),

            Button::make($this->isMaintenanceModeActive() ? 'Unlock System' : 'Lock System')
                ->icon($this->isMaintenanceModeActive() ? 'bs.lock' : 'bs.unlock')
                ->confirm($this->isMaintenanceModeActive()
                    ? 'This will disable the maintenance mode. The website will be available to all users.'
                    : 'This will put the application into maintenance mode. Only users with bypass access will be able to access the site.')
                ->method('toggleMaintenanceMode'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            TestingTabMenu::class,
            ...$this->buildTestSettingsLayout(),
        ];
    }

    /**
     * Build layout for test settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildTestSettingsLayout()
    {
        // Get system information
        Artisan::call('about');
        $aboutOutput = Artisan::output();

        return [
            Layout::block([
                Layout::rows([
                    Label::make('system_info_label')
                        ->title('System Information')
                        ->popover('<Artisan about> outputs a mixed collection of config and .env values, so not all config values are represented correctly.'),
                    Code::make('system_info')
                        ->language('shell')
                        ->readonly()
                        ->value($aboutOutput)
                        ->height('550px'),
                ]),

                Layout::rows([
                    Label::make('config_test_label')
                        ->title('Configuration Source Testing')
                        ->help('This shows where each configuration value is coming from: database, environment variables, or defaults')
                        ->addclass('fw-bold'),
                ]),

                // Use table layout with data from query
                Layout::table('config_data', [
                    TD::make('key', 'Configuration Key')
                        ->sort()
                        ->filter(Input::make())
                        ->width('200px')
                        ->render(function ($row) {
                            return $row['key'];
                        }),

                    TD::make('env_value', 'Environment Value')
                        ->width('250px')
                        ->render(function ($row) {
                            return $this->truncateValue($row['env_value']);
                        }),

                    TD::make('db_value', 'Database Value')
                        ->width('250px')
                        ->render(function ($row) {
                            return $this->truncateValue($row['db_value']);
                        }),

                    TD::make('config_value', 'Config Value')
                        ->width('250px')
                        ->render(function ($row) {
                            return $this->truncateValue($row['config_value']);
                        }),

                    TD::make('config_source', 'Source')
                        ->width('120px')
                        ->align(TD::ALIGN_CENTER)
                        ->render(function ($row) {
                            // Highlight the source with a badge
                            $sourceClass = match ($row['config_source']) {
                                'database' => 'bg-primary',
                                'environment' => 'bg-success',
                                'default' => 'bg-info',
                                default => 'bg-secondary'
                            };

                            return "<span class='badge {$sourceClass}'>{$row['config_source']}</span>";
                        }),
                ]),
            ])->vertical(),
        ];
    }

    /**
     * Helper function to truncate long values for display
     *
     * @param  string  $value
     * @param  int  $length
     * @return string
     */
    private function truncateValue($value, $length = 30)
    {
        if (is_string($value) && strlen($value) > $length) {
            return substr($value, 0, $length).'...';
        }

        return $value;
    }

    /**
     * Run the AppSettingsSeeder to import settings from configuration
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function runSettingsSeeder()
    {
        try {
            // Run the AppSettingsSeeder and capture the output
            \Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\AppSettingsSeeder',
            ]);

            // Capture the output directly
            $output = \Artisan::output();

            // Clear the configuration cache to activate new settings
            Artisan::call('config:clear');

            // Clear relevant caches
            Cache::flush();

            // Display the unmodified output as a success message
            Toast::success('Settings Seeder Output: '.PHP_EOL.$output);

            // Logging for diagnostic purposes
            Log::info('AppSettingsSeeder Output: '.$output);

        } catch (\Exception $e) {
            Log::error('Error running AppSettingsSeeder: '.$e->getMessage());
            Log::error($e->getTraceAsString());
            Toast::error('Failed to run settings seeder: '.$e->getMessage());
        }
    }

    /**
     * Check if the application is in maintenance mode
     */
    private function isMaintenanceModeActive(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * Toggle maintenance mode
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleMaintenanceMode()
    {
        if ($this->isMaintenanceModeActive()) {
            Artisan::call('up');
            Toast::success('Maintenance mode has been disabled.');
        } else {
            // Generate a secret bypass path
            $secret = 'admin-bypass-'.md5(now());

            Artisan::call('down', [
                '--refresh' => '60',  // Refresh the page every 60 seconds
                '--secret' => $secret,  // Secret URL path to bypass maintenance mode
            ]);

            // Generate the full URL for the admin bypass
            $bypassUrl = url($secret);

            Toast::info('Maintenance mode has been enabled. Admin bypass URL: '.$bypassUrl)
                ->persistent();

            // Log the link in case of any issues
            Log::info('Maintenance mode enabled with bypass URL: '.$bypassUrl);
        }
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
