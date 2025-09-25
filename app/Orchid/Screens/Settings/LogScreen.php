<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Models\Log;
use App\Orchid\Layouts\Settings\DatabaseLogsLayout;
use App\Orchid\Layouts\Settings\LoggingSettingsLayout;
use App\Orchid\Layouts\Settings\LogManagementTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LogScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(?Request $request = null): iterable
    {
        $query = Log::with('user');

        // Apply filters if request is provided
        if ($request) {
            if ($request->filled('filter.channel')) {
                $query->where('channel', 'like', '%'.$request->input('filter.channel').'%');
            }

            if ($request->filled('filter.level')) {
                $query->where('level', $request->input('filter.level'));
            }

            if ($request->filled('filter.message')) {
                $query->where('message', 'like', '%'.$request->input('filter.message').'%');
            }

            if ($request->filled('filter.logged_at')) {
                $query->whereDate('logged_at', $request->input('filter.logged_at'));
            }

            if ($request->filled('filter.user.name')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->input('filter.user.name').'%');
                });
            }

            // Debug: Check what sorting parameters Orchid sends
            // $allParams = $request->all();
            // if (!empty($allParams)) {
            //    \Log::info('Orchid Request Params:', $allParams);
            // }

            // Try different parameter names that Orchid might use
            $sortField = null;
            $sortDirection = 'desc';

            // Check various possible parameter names
            if ($request->filled('sort')) {
                $sortValue = $request->input('sort');

                // Handle case where sort might be "-fieldname" for descending
                if (is_string($sortValue) && str_starts_with($sortValue, '-')) {
                    $sortField = substr($sortValue, 1);
                    $sortDirection = 'desc';
                } else {
                    $sortField = $sortValue;
                    $sortDirection = $request->input('direction', 'asc');
                }
            } elseif ($request->filled('order')) {
                $sortValue = $request->input('order');

                // Handle case where order might be "-fieldname" for descending
                if (is_string($sortValue) && str_starts_with($sortValue, '-')) {
                    $sortField = substr($sortValue, 1);
                    $sortDirection = 'desc';
                } else {
                    $sortField = $sortValue;
                    $sortDirection = $request->input('direction', 'asc');
                }
            }

            // Validate sort field to prevent SQL injection
            $allowedSortFields = ['channel', 'level', 'message', 'logged_at', 'user.name'];
            if ($sortField && in_array($sortField, $allowedSortFields)) {
                // Handle nested sorting for user.name
                if ($sortField === 'user.name') {
                    $query->leftJoin('users', 'logs.user_id', '=', 'users.id')
                        ->orderBy('users.name', $sortDirection)
                        ->select('logs.*');
                } else {
                    $query->orderBy($sortField, $sortDirection);
                }
            } else {
                // Default sorting
                $query->orderBy('logged_at', 'desc');
            }
        } else {
            // Default sorting when no request
            $query->orderBy('logged_at', 'desc');
        }

        return [
            'logging_settings' => AppSetting::where('group', 'logging')->get(),
            'database_logs' => $query->limit(100)->get(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Log Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Configure logging settings and view system logs.';
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.log',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        $currentRoute = request()->route()->getName();
        $buttons = [];

        if ($currentRoute === 'platform.settings.log.configuration') {
            // Configuration tab buttons
            $buttons[] = Button::make('Save')
                ->icon('save')
                ->method('saveSettings');
        } else {
            // System Log tab buttons
            if (config('app.env') !== 'production') {
                $buttons[] = Button::make('Clear')
                    ->icon('trash')
                    ->method('clearDatabaseLogs')
                    ->confirm('Are you sure you want to delete all database logs? This action cannot be undone.');
            }

            $buttons[] = Button::make('Refresh')
                ->icon('arrow-clockwise')
                ->method('refreshLog');
        }

        return $buttons;
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        $currentRoute = request()->route()->getName();

        $layouts = [
            LogManagementTabMenu::class,
        ];

        if ($currentRoute === 'platform.settings.log.configuration') {
            $layouts = array_merge($layouts, LoggingSettingsLayout::build($this->query()['logging_settings']));
        } else {
            $layouts[] = DatabaseLogsLayout::class;
        }

        // Modal for showing context details (only for System Log tab)
        if ($currentRoute !== 'platform.settings.log.configuration') {
            $layouts[] = Layout::modal('showContext', [
                // Content Details (full width)
                Layout::rows([
                    Label::make('message')
                        ->title('Message')
                        ->class('fw-bold'), // Bootstrap bold class

                    // Context Section - Code field with text mode for proper line breaks
                    Code::make('context')
                        ->title('Context Data')
                        ->language('javascript') // Use javascript for JSON syntax highlighting
                        ->readonly(true)
                        ->lineNumbers()
                        ->help('Context data in JSON format'),

                    // Stack Trace Section
                    Code::make('stack_trace')
                        ->title('Stack Trace')
                        ->language('text')
                        ->readonly(true)
                        ->height('300px'),
                ]),
                // Header Information (two columns)
                Layout::columns([
                    Layout::rows([
                        Label::make('log_channel')
                            ->title('Channel'),
                    ]),

                    Layout::rows([
                        Label::make('log_level')
                            ->title('Level'),
                    ]),
                    Layout::rows([
                        Label::make('log_time')
                            ->title('Time'),
                    ]),
                    Layout::rows([
                        Label::make('error_code')
                            ->title('Error Code'),
                    ]),
                    Layout::rows([
                        Label::make('log_user')
                            ->title('User'),
                    ]),
                ]),

                Layout::rows([
                    Label::make('log_user_agent')
                        ->title('User Agent'),
                ]),

            ])
                ->title('Log Details')
                ->size(\Orchid\Screen\Layouts\Modal::SIZE_XL)
                ->withoutApplyButton()
                ->withoutCloseButton()
                ->deferred('showContext');
        }

        return $layouts;
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
                'action' => 'test_database_log',
            ]);

            Toast::info('Test log entry has been created successfully!');
        } catch (\Exception $e) {
            Toast::error('Failed to create test log: '.$e->getMessage());
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
            Toast::error('Failed to clear logs: '.$e->getMessage());
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

        if (! $log) {
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

        // Return context as formatted JSON string for proper line breaks
        $contextData = 'No context data available';
        if (! empty($log->context) && is_array($log->context)) {
            // Format as pretty JSON string with proper line breaks
            $contextData = json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }        // Extract error code from message if present
        $errorCode = 'N/A';
        if (preg_match('/\((?:Error\()?code:\s*(\d+)\)/i', $log->message, $matches)) {
            $errorCode = $matches[1];
        }

        // Prepare message and stack_trace output. Prefer stored stack_trace.
        $message = $log->message;
        $stackTrace = $log->stack_trace ?? null;

        // If no stored stack trace, try to split it from the message
        if (empty($stackTrace)) {
            // Look for "Stack trace:" marker
            $markerPos = stripos($message, 'Stack trace:');

            if ($markerPos !== false) {
                $stackTrace = trim(substr($message, $markerPos));
                $message = trim(substr($message, 0, $markerPos));
            } else {
                // Look for lines starting with #0 (common PHP trace)
                if (preg_match('/\n#0\s+/s', $message, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1];
                    $stackTrace = trim(substr($message, $pos));
                    $message = trim(substr($message, 0, $pos));
                }
            }
        }

        // Final fallbacks
        if (empty($message)) {
            $message = $log->message; // ensure message never empty
        }

        if (empty($stackTrace)) {
            $stackTrace = 'Kein Stack Trace verfügbar';
        }

        return [
            'log_channel' => $log->channel,
            'log_level' => strtoupper($log->level),
            'log_time' => $log->logged_at->format('d.m.Y H:i:s'),
            'log_user' => $log->user ? $log->user->name : 'System',
            'log_ip' => $log->remote_addr ?? 'N/A',
            'log_user_agent' => $log->user_agent ?? 'N/A',
            'error_code' => $errorCode,
            'message' => $message, // Clean message without HTML
            'context' => $contextData,
            'stack_trace' => $stackTrace,
        ];
    }
}
