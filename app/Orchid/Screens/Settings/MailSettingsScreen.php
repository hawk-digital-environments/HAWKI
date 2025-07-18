<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MailSettingsScreen extends Screen
{
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
        $mailSettings = AppSetting::where('group', 'mail')->get();

        return [
            'mail' => $mailSettings,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Mail Settings';
    }

    public function description(): ?string
    {
        return 'Configure mail server and email settings.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('saveSettings')
                ->confirm('Save all mail settings?'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $mailSettings = $this->buildMailSettingsLayout();
        $emailTesting = $this->buildEmailTestingLayout();
        
        return [
            Layout::tabs([
                'Mail Configuration' => $mailSettings,
                'Email Testing' => $emailTesting,
            ]),
        ];
    }

    /**
     * Build layout for mail settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildMailSettingsLayout()
    {
        $generalSettings = [];
        $smtpSettings = [];
        $herdSettings = [];
        $sendmailSettings = [];
        $logSettings = [];

        // Gruppiere die Einstellungen nach Kategorien entsprechend der settings.php
        foreach ($this->query()['mail'] as $setting) {
            $key = $setting->key;
            
            // General Mail Settings
            if (in_array($key, ['mail_default', 'mail_from_address', 'mail_from_name'])) {
                $generalSettings[] = $this->getFieldForSetting($setting);
            }
            // SMTP Mailer Configuration
            else if (str_contains($key, 'mail_mailers_smtp_')) {
                $smtpSettings[] = $this->getFieldForSetting($setting);
            }
            // Herd Mailer Configuration
            else if (str_contains($key, 'mail_mailers_herd_')) {
                $herdSettings[] = $this->getFieldForSetting($setting);
            }
            // Sendmail Configuration
            else if (str_contains($key, 'mail_mailers_sendmail_')) {
                $sendmailSettings[] = $this->getFieldForSetting($setting);
            }
            // Log Mailer Configuration
            else if (str_contains($key, 'mail_mailers_log_')) {
                $logSettings[] = $this->getFieldForSetting($setting);
            }
            // Legacy keys (backwards compatibility)
            else if (in_array($key, ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password'])) {
                $generalSettings[] = $this->getFieldForSetting($setting);
            }
            // Fallback für unbekannte Keys
            else {
                $generalSettings[] = $this->getFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];
        
        // General Mail Settings
        if (!empty($generalSettings)) {
            $layouts[] = Layout::rows($generalSettings)
                ->title('General Mail Settings');
        }
        
        // SMTP Configuration
        if (!empty($smtpSettings)) {
            $layouts[] = Layout::rows($smtpSettings)
                ->title('SMTP Mailer Configuration');
        }
        
        // Herd Configuration
        if (!empty($herdSettings)) {
            $layouts[] = Layout::rows($herdSettings)
                ->title('Herd Mailer Configuration (Local Development)');
        }
        
        // Sendmail Configuration
        if (!empty($sendmailSettings)) {
            $layouts[] = Layout::rows($sendmailSettings)
                ->title('Sendmail Configuration');
        }
        
        // Log Configuration
        if (!empty($logSettings)) {
            $layouts[] = Layout::rows($logSettings)
                ->title('Log Mailer Configuration');
        }
        
        return $layouts;
    }

    /**
     * Build layout for email testing
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildEmailTestingLayout()
    {
        return [
            Layout::rows([
                Label::make('email_test_label')
                    ->title('Email Testing')
                    ->help('Test the email functionality of the system')
                    ->addclass('fw-bold'),
                    
                Group::make([
                    Button::make('Send Test Email')
                        ->icon('bs.envelope-check')
                        ->method('sendTestMail')
                        ->confirm('Send a simple test email to verify mail configuration?'),
                        
                    Button::make('Send Welcome Email (Sync)')
                        ->icon('bs.envelope')
                        ->method('sendWelcomeEmailTestSync')
                        ->confirm('This will send a welcome email synchronously (bypassing queue). Continue?'),
                        
                    Button::make('Send Welcome Email (Queue)')
                        ->icon('bs.envelope-plus')
                        ->method('sendWelcomeEmailTest')
                        ->confirm('This will queue a welcome email (requires queue worker). Continue?'),
                        
                    Button::make('Send OTP Email')
                        ->icon('bs.shield')
                        ->method('sendOtpEmailTest')
                        ->confirm('This will send an OTP (One-Time Password) email to the current authenticated user. Continue?'),
                ])->autoWidth(),
            ])->title('Email Testing'),
            
            Layout::rows([
                Label::make('queue_status_label')
                    ->title('Queue Status')
                    ->help('Check the status of the queue system')
                    ->addclass('fw-bold'),
                    
                Group::make([
                    Button::make('Check Queue Status')
                        ->icon('bs.list-check')
                        ->method('checkQueueStatus')
                        ->confirm('Check the current queue status and jobs?'),
                        
                    Button::make('Process Emails Queue')
                        ->icon('bs.envelope-arrow-up')
                        ->method('processMailQueueManually')
                        ->confirm('Manually process pending emails queue jobs only?'),
                        
                    Button::make('Process All Queues')
                        ->icon('bs.play-circle')
                        ->method('processQueueManually')
                        ->confirm('Manually process pending queue jobs from all queues (emails & default)?'),
                        
                    Button::make('Clear Emails Queue')
                        ->icon('bs.trash')
                        ->method('clearEmailsQueue')
                        ->confirm('⚠️ This will DELETE all pending jobs in the emails queue. This action cannot be undone! Continue?'),
                        
                    Button::make('Clear All Queues')
                        ->icon('bs.trash3')
                        ->method('clearAllQueues')
                        ->confirm('⚠️ This will DELETE all pending jobs in ALL queues (emails & default). This action cannot be undone! Continue?'),
                ])->autoWidth(),
            ])->title('Queue Management'),
        ];
    }

    /**
     * Create the appropriate form field based on setting type
     *
     * @param AppSetting $setting
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    private function getFieldForSetting(AppSetting $setting)
    {
        $key = $setting->key;
        
        // Generiere den korrekten Laravel Config-Namen
        $displayKey = $this->getConfigKeyFromDbKey($key);

        switch ($setting->type) {
            case 'boolean':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Switcher::make("settings.{$key}")
                        ->sendTrueOrFalse()
                        ->value($setting->typed_value),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
                    
            case 'integer':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->type('number')
                        ->value($setting->value)
                        ->horizontal(),
                ])
                ->widthColumns('1fr 1fr');
                    
            case 'json':
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    TextArea::make("settings.{$key}")
                        ->rows(10)
                        ->value(json_encode($setting->typed_value, JSON_PRETTY_PRINT))
                        ->style('min-width: 100%; resize: vertical;'),  
                ])
                ->widthColumns('1fr 1fr');
            
            case 'string':
            default:
                // Special handling for password fields
                if (str_contains($key, 'password')) {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Input::make("settings.{$key}")
                            ->type('password')
                            ->value(''),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                
                // Special handling for mail driver selection
                if ($key === 'mail_default' || $key === 'mail_mailer') {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Select::make("settings.{$key}")
                            ->options([
                                'smtp' => 'SMTP',
                                'herd' => 'Herd (Local Development)',
                                'sendmail' => 'Sendmail',
                                'log' => 'Log (for testing)',
                                'array' => 'Array (for testing)',
                                'mailgun' => 'Mailgun',
                                'ses' => 'Amazon SES',
                                'postmark' => 'Postmark',
                            ])
                            ->value($setting->value),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                
                // Special handling for encryption
                if (str_contains($key, 'encryption')) {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Select::make("settings.{$key}")
                            ->options([
                                '' => 'None',
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                            ])
                            ->value($setting->value)
                            ->empty('None'),
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                
                // Special handling for transport type
                if (str_contains($key, 'transport')) {
                    return Group::make([
                        Label::make("label_{$key}")
                            ->title($setting->description)
                            ->help($displayKey)
                            ->addclass('fw-bold'),
                        Select::make("settings.{$key}")
                            ->options([
                                'smtp' => 'SMTP',
                                'sendmail' => 'Sendmail',
                                'log' => 'Log',
                            ])
                            ->value($setting->value)
                            ->readonly(), // Transport type should match the mailer name
                    ])
                    ->alignCenter()
                    ->widthColumns('1fr 1fr');
                }
                
                return Group::make([
                    Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    Input::make("settings.{$key}")
                        ->value($setting->value),
                ])
                ->alignCenter()
                ->widthColumns('1fr 1fr');
        }
    }

    /**
     * Convert database key format to Laravel config key format
     * 
     * @param string $dbKey Database key with underscore notation (e.g., 'mail_mailers_smtp_host')
     * @return string Config key in dot notation (e.g., 'mail.mailers.smtp.host')
     */
    private function getConfigKeyFromDbKey(string $dbKey): string
    {
        // Ersetze alle Unterstriche durch Punkte, um die korrekte Config-Notation zu erhalten
        return str_replace('_', '.', $dbKey);
    }

    /**
     * Save settings to the database
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        $count = 0;
        
        // Debug: Log all received settings for troubleshooting
        Log::info('Received mail settings in saveSettings:', $settings);
        
        if ($settings) {
            // Collect only entries that have actually changed
            $changedSettings = [];
            
            foreach ($settings as $key => $value) {
                // Skip empty password fields
                if (str_contains($key, 'password') && empty($value)) {
                    continue;
                }
                
                $existingSetting = AppSetting::where('key', $key)->first();
                
                if ($existingSetting) {
                    $normalizedNewValue = $this->normalizeValue($value, $existingSetting->type);
                    $normalizedOldValue = $this->normalizeValue($existingSetting->value, $existingSetting->type);
                    
                    if ($normalizedNewValue !== $normalizedOldValue) {
                        $changedSettings[] = [
                            'key' => $key,
                            'value' => $normalizedNewValue,
                            'type' => $existingSetting->type,
                            'model' => $existingSetting
                        ];
                    }
                } else {
                    Log::warning("Mail setting not found: {$key}");
                }
            }
            
            // Perform updates only for changed settings
            foreach ($changedSettings as $changed) {
                $setting = $changed['model'];
                $formattedValue = $this->formatValueForStorage($changed['value'], $changed['type']);
                
                $setting->value = $formattedValue;
                $setting->save();
                $count++;
                
                Log::info("Updated mail setting: {$changed['key']} to '{$formattedValue}'");
            }
            
            // User feedback based on the changes
            if ($count > 0) {
                try {
                    // Clear config cache to apply changes
                    Artisan::call('config:clear');
                    
                    // Clear the specific config override cache
                    \App\Providers\ConfigServiceProvider::clearConfigCache();
                    
                    Toast::success("{$count} mail setting(s) have been updated, and the configuration cache has been cleared.");
                } catch (\Exception $e) {
                    Toast::warning("Mail settings saved, but clearing cache failed: " . $e->getMessage());
                }
            } else {
                Toast::info('No changes detected in mail settings.');
            }
        }
        
        return;
    }

    /**
     * Send a test email
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTestMail()
    {
        try {
            $user = auth()->user();
            
            if (!$user || !$user->email) {
                Toast::error('No authenticated user with email address found.');
                return;
            }

            // Send a simple test email
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from HAWKI to verify your mail configuration is working correctly.',
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('HAWKI Mail Configuration Test');
                }
            );
            
            Toast::success("Test email has been sent to: {$user->email}")
                ->autoHide(false);
                
            Log::info("Test email sent to user: {$user->username} ({$user->email})");
            
        } catch (\Exception $e) {
            Log::error('Error sending test email: ' . $e->getMessage());
            Toast::error('Failed to send test email: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Send a welcome email test (queued)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendWelcomeEmailTest()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Toast::error('No authenticated user found for email test.');
                return;
            }

            if (!$user->email) {
                Toast::error('No email address found for current user.');
                return;
            }

            // Use the MailController's sendWelcomeEmail method (this will queue the email)
            $mailController = new \App\Http\Controllers\MailController();
            $mailController->sendWelcomeEmail($user);
            
            Toast::info("Welcome email has been queued for: {$user->email}. Check queue status to see if it's processed.")
                ->autoHide(false);
                
            Log::info("Welcome email test (queued) for user: {$user->username} ({$user->email})");
            
        } catch (\Exception $e) {
            Log::error('Error queuing welcome email test: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Toast::error('Failed to queue welcome email: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Send a welcome email test (synchronous - bypasses queue)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendWelcomeEmailTestSync()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Toast::error('No authenticated user found for email test.');
                return;
            }

            if (!$user->email) {
                Toast::error('No email address found for current user.');
                return;
            }

            // Send welcome email synchronously (bypass queue)
            \Illuminate\Support\Facades\Mail::to($user->email)->sendNow(new \App\Mail\WelcomeMail($user));
            
            Toast::success("Welcome email has been sent synchronously to: {$user->email}")
                ->autoHide(false);
                
            Log::info("Welcome email test (sync) sent to user: {$user->username} ({$user->email})");
            
        } catch (\Exception $e) {
            Log::error('Error sending welcome email test (sync): ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Toast::error('Failed to send welcome email (sync): ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Send an OTP email test
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendOtpEmailTest()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Toast::error('No authenticated user found for OTP email test.');
                return;
            }

            // Create user info array similar to what's used in the handshake process
            $userInfo = [
                'username' => $user->username ?? $user->name,
                'email' => $user->email,
                'name' => $user->name ?? $user->username
            ];

            // Generate test OTP
            $testOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $appName = config('app.name', 'HAWKI');

            // Send OTP email synchronously for testing (bypass queue)
            \Illuminate\Support\Facades\Mail::to($user->email)->sendNow(new \App\Mail\OTPMail($userInfo, $testOtp, $appName));

            Toast::success("OTP email test has been sent to: {$user->email}. Test OTP: {$testOtp}")
                ->autoHide(false);
                
            Log::info("OTP email test sent to user: {$user->username} ({$user->email}) with test OTP: {$testOtp}");
            
        } catch (\Exception $e) {
            Log::error('Error sending OTP email test: ' . $e->getMessage());
            Toast::error('Failed to send OTP email: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Check queue status and pending jobs
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkQueueStatus()
    {
        try {
            // Get queue connection info
            $queueConnection = config('queue.default');
            
            // Check if we can get queue size (works for database queue)
            $defaultPendingJobs = 'Unknown';
            $emailsPendingJobs = 'Unknown';
            if ($queueConnection === 'database') {
                try {
                    $defaultPendingJobs = \DB::table('jobs')->where('queue', 'default')->count();
                    $emailsPendingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
                } catch (\Exception $e) {
                    $defaultPendingJobs = 'Error: ' . $e->getMessage();
                    $emailsPendingJobs = 'Error: ' . $e->getMessage();
                }
            }
            
            // Try to get failed jobs count
            $failedJobs = 'Unknown';
            try {
                $failedJobs = \DB::table('failed_jobs')->count();
            } catch (\Exception $e) {
                $failedJobs = 'Error: ' . $e->getMessage();
            }
            
            $message = "Queue Status:\n" .
                      "Connection: {$queueConnection}\n" .
                      "Default Queue Jobs: {$defaultPendingJobs}\n" .
                      "Emails Queue Jobs: {$emailsPendingJobs}\n" .
                      "Failed Jobs: {$failedJobs}";
            
            Toast::info($message)->autoHide(false);
            Log::info("Queue status check: {$message}");
            
        } catch (\Exception $e) {
            Log::error('Error checking queue status: ' . $e->getMessage());
            Toast::error('Failed to check queue status: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Manually process queue jobs
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processQueueManually()
    {
        try {
            // Process emails queue first
            $emailsExitCode = Artisan::call('queue:work', [
                '--queue' => 'emails,default',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3
            ]);
            
            $output = Artisan::output();
            
            if ($emailsExitCode === 0) {
                Toast::success("Queue processing completed successfully (emails & default queues).\nOutput: " . $output)
                    ->autoHide(false);
            } else {
                Toast::warning("Queue processing finished with exit code: {$emailsExitCode}\nOutput: " . $output)
                    ->autoHide(false);
            }
            
            Log::info("Manual queue processing (emails & default) - Exit code: {$emailsExitCode}, Output: {$output}");
            
        } catch (\Exception $e) {
            Log::error('Error processing queue manually: ' . $e->getMessage());
            Toast::error('Failed to process queue: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Manually process emails queue jobs only
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processMailQueueManually()
    {
        try {
            // Process only emails queue
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'emails',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3
            ]);
            
            $output = Artisan::output();
            
            if ($exitCode === 0) {
                Toast::success("Emails queue processing completed successfully.\nOutput: " . $output)
                    ->autoHide(false);
            } else {
                Toast::warning("Emails queue processing finished with exit code: {$exitCode}\nOutput: " . $output)
                    ->autoHide(false);
            }
            
            Log::info("Manual emails queue processing - Exit code: {$exitCode}, Output: {$output}");
            
        } catch (\Exception $e) {
            Log::error('Error processing emails queue manually: ' . $e->getMessage());
            Toast::error('Failed to process emails queue: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Clear all jobs from the emails queue
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearEmailsQueue()
    {
        try {
            $queueConnection = config('queue.default');
            $deletedJobs = 0;
            
            if ($queueConnection === 'database') {
                // For database queue, delete directly from jobs table
                $deletedJobs = \DB::table('jobs')->where('queue', 'emails')->count();
                \DB::table('jobs')->where('queue', 'emails')->delete();
                
                Toast::success("Successfully cleared emails queue. Deleted {$deletedJobs} job(s).")
                    ->autoHide(false);
                    
                Log::info("Cleared emails queue - Deleted {$deletedJobs} jobs");
            } else {
                // For other queue types, use Artisan command
                $exitCode = Artisan::call('queue:clear', ['--queue' => 'emails']);
                $output = Artisan::output();
                
                if ($exitCode === 0) {
                    Toast::success("Emails queue cleared successfully.\nOutput: " . $output)
                        ->autoHide(false);
                } else {
                    Toast::warning("Queue clear finished with exit code: {$exitCode}\nOutput: " . $output)
                        ->autoHide(false);
                }
                
                Log::info("Cleared emails queue via Artisan - Exit code: {$exitCode}, Output: {$output}");
            }
            
        } catch (\Exception $e) {
            Log::error('Error clearing emails queue: ' . $e->getMessage());
            Toast::error('Failed to clear emails queue: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Clear all jobs from all queues
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearAllQueues()
    {
        try {
            $queueConnection = config('queue.default');
            $deletedJobs = 0;
            
            if ($queueConnection === 'database') {
                // For database queue, delete directly from jobs table
                $deletedJobs = \DB::table('jobs')->count();
                \DB::table('jobs')->delete();
                
                Toast::success("Successfully cleared all queues. Deleted {$deletedJobs} job(s).")
                    ->autoHide(false);
                    
                Log::info("Cleared all queues - Deleted {$deletedJobs} jobs");
            } else {
                // For other queue types, use Artisan command
                $exitCode = Artisan::call('queue:clear');
                $output = Artisan::output();
                
                if ($exitCode === 0) {
                    Toast::success("All queues cleared successfully.\nOutput: " . $output)
                        ->autoHide(false);
                } else {
                    Toast::warning("Queue clear finished with exit code: {$exitCode}\nOutput: " . $output)
                        ->autoHide(false);
                }
                
                Log::info("Cleared all queues via Artisan - Exit code: {$exitCode}, Output: {$output}");
            }
            
        } catch (\Exception $e) {
            Log::error('Error clearing all queues: ' . $e->getMessage());
            Toast::error('Failed to clear all queues: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Normalize a value for comparison based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function normalizeValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'integer':
                return (int) $value;
                
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return $decoded !== null ? $decoded : $value;
                }
                return $value;
                
            default:
                return (string) $value;
        }
    }

    /**
     * Format a value for database storage based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    private function formatValueForStorage($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
                
            case 'integer':
                return (string) $value;
                
            case 'json':
                return is_string($value) ? $value : json_encode($value);
                
            default:
                return (string) $value;
        }
    }
}
