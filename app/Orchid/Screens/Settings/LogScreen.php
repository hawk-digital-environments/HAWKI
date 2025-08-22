<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Models\Log;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LogScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        return [
            'logging_settings' => AppSetting::where('group', 'logging')->get(),
            'database_logs' => Log::with('user')->orderBy('logged_at', 'desc')->limit(100)->get()
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'System Logs';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return 'Configure logging settings and view system logs';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            // Database Log Actions
            Button::make('Test Log')
                ->icon('database')
                ->method('testDatabaseLog'),
            Button::make('Clear')
                ->icon('trash')
                ->method('clearDatabaseLogs')
                ->confirm('Are you sure you want to delete all database logs? This action cannot be undone.'),
            
            // General Actions
            Button::make('Refresh')
                ->icon('arrow-clockwise')
                ->method('refreshLog'),
            Button::make('Save')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        $loggingConfigLayout = $this->buildLoggingSettingsLayout();
        $databaseLogsLayout = $this->buildDatabaseLogsLayout();

        return [
            Layout::tabs([
                'System Log' => $databaseLogsLayout,
                'Configuration' => $loggingConfigLayout,
            ]),

            // Modal for showing context details
            Layout::modal('showContext', [
                Layout::columns([
                    Layout::rows([
                        Label::make('log_channel')
                            ->title('Channel'),
                        Label::make('log_level')
                            ->title('Level'),
                        Label::make('log_time')
                            ->title('Time'),
                        Label::make('error_code')
                            ->title('Error Code'),
    ]),
                    Layout::rows([
                        Label::make('log_user')
                            ->title('User'),
                        Label::make('log_ip')
                            ->title('IP-Address'),
                        Label::make('log_user_agent')
                            ->title('User Agent'),
                    ]),
                ]),
                Layout::rows([
                    // Message Section
                    Label::make('message')
                        ->title('Message'),
                    
                    // Context Section
                    Label::make('context')
                        ->title('Context'),
                    
                    // Stack Trace Section
                    Code::make('stack_trace')
                        ->title('Stack Trace')
                        ->language('text')
                        ->readonly(true)
                        ->height('300px'),
                ])
            ])
                ->title('Log Details')
                ->size(Modal::SIZE_XL)
                ->withoutApplyButton()
                ->withoutCloseButton()
                ->deferred('showContext'),
        ];
    }

    /**
     * Build layout for logging settings
     */
    private function buildLoggingSettingsLayout()
    {
        $fields = [];

        foreach ($this->query()['logging_settings'] as $setting) {
            $fields[] = $this->generateFieldForSetting($setting);
        }

        return Layout::rows($fields);
    }

    /**
     * Build layout for database logs display
     */
    private function buildDatabaseLogsLayout()
    {
        return Layout::table('database_logs', [
            TD::make('level', 'Level')
                ->width('80px')
                ->render(function ($log) {
                    $colors = [
                        'debug' => 'text-muted',
                        'info' => 'text-info',
                        'warning' => 'text-warning', 
                        'error' => 'text-danger',
                        'critical' => 'text-danger bg-danger-subtle'
                    ];
                    $class = $colors[$log->level] ?? 'text-dark';
                    return "<span class='{$class}'>" . strtoupper($log->level) . "</span>";
                }),
            TD::make('message', 'Message')
                ->width('400px')
                ->render(function ($log) {
                    $messagePreview = \Str::limit($log->message, 100);
                    
                    return ModalToggle::make($messagePreview)
                        ->modal('showContext')
                        ->modalTitle('Log Details')
                        ->method('showContext')
                        ->asyncParameters([
                            'log_id' => $log->id
                        ])
                        ->class('btn btn-link btn-sm p-0 text-start text-decoration-none')
                        ->style('white-space: normal; word-wrap: break-word;');
                }),
            TD::make('channel', 'Channel')
                ->width('100px')
                ->render(function ($log) {
                    return "<small class='text-muted'>{$log->channel}</small>";
                }),
            TD::make('logged_at', 'Time')
                ->width('150px')
                ->render(function ($log) {
                    return "<small>" . $log->logged_at->format('Y-m-d H:i:s') . "</small>";
                }),
            TD::make('user.name', 'User')
                ->width('120px')
                ->render(function ($log) {
                    return $log->user ? "<small>{$log->user->name}</small>" : '<small class="text-muted">System</small>';
                }),
        ]);
    }

    /**
     * Test database logging functionality
     */
    public function testDatabaseLog()
    {
        try {
            LaravelLog::channel('database')->info('Test log entry from LogScreen', [
                'test' => true,
                'timestamp' => now(),
                'screen' => 'LogScreen',
                'action' => 'test_database_log'
            ]);

            Toast::info('Test log entry has been created successfully!');
        } catch (\Exception $e) {
            Toast::error('Failed to create test log: ' . $e->getMessage());
        }
    }

    /**
     * Clear all database logs
     */
    public function clearDatabaseLogs()
    {
        try {
            $count = Log::count();
            Log::truncate();
            Toast::info("Successfully deleted {$count} log entries.");
        } catch (\Exception $e) {
            Toast::error('Failed to clear logs: ' . $e->getMessage());
        }
    }

    /**
     * Refresh the log data
     */
    public function refreshLog()
    {
        Toast::info('Log data refreshed');
    }

    /**
     * Show context details in modal
     */
    public function showContext(Request $request): array
    {
        $logId = $request->get('log_id');
        $log = Log::with('user')->find($logId);
        
        if (!$log) {
            return [
                'log_channel' => 'N/A',
                'log_level' => 'ERROR',
                'log_time' => 'N/A',
                'log_user' => 'N/A',
                'log_ip' => 'N/A',
                'log_user_agent' => 'N/A',
                'error_code' => 'N/A',
                'message' => 'Log entry not found',
                'context' => 'Log entry not found',
                'stack_trace' => 'No stack trace available',
            ];
        }
        
        // Format context data as readable text instead of JSON
        $contextText = 'No context data available';
        if (!empty($log->context)) {
            $contextText = '';
            foreach ($log->context as $key => $value) {
                if (is_array($value)) {
                    $contextText .= "{$key}: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
                } else {
                    $contextText .= "{$key}: {$value}\n";
                }
            }
        }
        
        // Extract error code from message if present
        $errorCode = 'N/A';
        if (preg_match('/\((?:Error\()?code:\s*(\d+)\)/i', $log->message, $matches)) {
            $errorCode = $matches[1];
        }
        
        return [
            'log_channel' => $log->channel,
            'log_level' => strtoupper($log->level),
            'log_time' => $log->logged_at->format('d.m.Y H:i:s'),
            'log_user' => $log->user ? $log->user->name : 'System',
            'log_ip' => $log->remote_addr ?? 'N/A',
            'log_user_agent' => $log->user_agent ?? 'N/A',
            'error_code' => $errorCode,
            'message' => $log->message,
            'context' => $contextText,
            'stack_trace' => $log->stack_trace ?? 'Kein Stack Trace verfügbar'
        ];
    }
}
