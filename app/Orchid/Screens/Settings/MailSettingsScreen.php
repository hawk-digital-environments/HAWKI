<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Services\SettingsService;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
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
                ->method('saveSettings'),        ];
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
            
            // General Mail Settings (default mailer, from address/name)
            if (in_array($key, ['mail_default']) || str_starts_with($key, 'mail_from.')) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
            // SMTP Mailer Configuration
            else if (str_starts_with($key, 'mail_mailers.smtp.')) {
                $smtpSettings[] = $this->generateFieldForSetting($setting);
            }
            // Herd Mailer Configuration
            else if (str_starts_with($key, 'mail_mailers.herd.')) {
                $herdSettings[] = $this->generateFieldForSetting($setting);
            }
            // Sendmail Configuration
            else if (str_starts_with($key, 'mail_mailers.sendmail.')) {
                $sendmailSettings[] = $this->generateFieldForSetting($setting);
            }
            // Log Mailer Configuration
            else if (str_starts_with($key, 'mail_mailers.log.')) {
                $logSettings[] = $this->generateFieldForSetting($setting);
            }
            // Legacy keys (backwards compatibility)
            else if (in_array($key, ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password'])) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
            // Fallback für unbekannte Keys
            else {
                $generalSettings[] = $this->generateFieldForSetting($setting);
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

            Layout::rows([
                // Debug/Output area
                Code::make('email_test_output')
                    ->language('text')
                    ->readonly()
                    ->value(session('email_test_output', 'No output yet. Run a queue operation to see results here.'))
                    ->height('300px')
                    ->help('Email test output and debug information will appear here'),
            ])->title('Debug Output'),
        ];
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
    public function checkQueueStatus(Request $request)
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Queue Status Check\n";
            $outputText .= str_repeat('=', 50) . "\n\n";
            
            // Get queue connection info
            $queueConnection = config('queue.default');
            $outputText .= "Queue Connection: {$queueConnection}\n\n";
            
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
            
            $outputText .= "📊 Queue Statistics:\n";
            $outputText .= "  • Default Queue Jobs: {$defaultPendingJobs}\n";
            $outputText .= "  • Emails Queue Jobs: {$emailsPendingJobs}\n";
            $outputText .= "  • Failed Jobs: {$failedJobs}\n\n";
            
            // Add configuration details
            $outputText .= "⚙️ Configuration:\n";
            $outputText .= "  • Queue Driver: " . config('queue.connections.' . $queueConnection . '.driver', 'N/A') . "\n";
            $outputText .= "  • Queue Table: " . config('queue.connections.' . $queueConnection . '.table', 'N/A') . "\n\n";
            
            $outputText .= "[$timestamp] Status check completed.\n";
            
            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);
            
            $message = "Default Queue Jobs: {$defaultPendingJobs}, Emails Queue Jobs: {$emailsPendingJobs}, Failed Jobs: {$failedJobs}";
            Toast::info($message)->autoHide(false);
            Log::info("Queue status check: {$message}");
            
        } catch (\Exception $e) {
            $errorOutput = "❌ Error checking queue status: " . $e->getMessage() . "\n";
            $errorOutput .= "Stack trace:\n" . $e->getTraceAsString();
            
            session(['email_test_output' => $errorOutput]);
            
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
    public function processQueueManually(Request $request)
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Starting queue processing (emails & default)...\n\n";
            
            // Process emails queue first
            $emailsExitCode = Artisan::call('queue:work', [
                '--queue' => 'emails,default',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3
            ]);
            
            $artisanOutput = Artisan::output();
            $outputText .= "Exit Code: {$emailsExitCode}\n";
            $outputText .= "Command Output:\n";
            $outputText .= str_repeat('-', 50) . "\n";
            $outputText .= $artisanOutput;
            $outputText .= "\n" . str_repeat('-', 50) . "\n";
            
            if ($emailsExitCode === 0) {
                $outputText .= "✅ Queue processing completed successfully!\n";
                Toast::success("Queue processing completed successfully (emails & default queues).")
                    ->autoHide(false);
            } else {
                $outputText .= "⚠️ Queue processing finished with exit code: {$emailsExitCode}\n";
                Toast::warning("Queue processing finished with exit code: {$emailsExitCode}")
                    ->autoHide(false);
            }
            
            $outputText .= "\n[$timestamp] Process completed.\n";
            
            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);
            
            Log::info("Manual queue processing (emails & default) - Exit code: {$emailsExitCode}, Output: {$artisanOutput}");
            
        } catch (\Exception $e) {
            $errorOutput = "❌ Error processing queue: " . $e->getMessage() . "\n";
            $errorOutput .= "Stack trace:\n" . $e->getTraceAsString();
            
            session(['email_test_output' => $errorOutput]);
            
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
    public function processMailQueueManually(Request $request)
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Starting emails queue processing...\n\n";
            
            // Process only emails queue
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'emails',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3
            ]);
            
            $artisanOutput = Artisan::output();
            $outputText .= "Exit Code: {$exitCode}\n";
            $outputText .= "Command Output:\n";
            $outputText .= str_repeat('-', 50) . "\n";
            $outputText .= $artisanOutput;
            $outputText .= "\n" . str_repeat('-', 50) . "\n";
            
            if ($exitCode === 0) {
                $outputText .= "✅ Emails queue processing completed successfully!\n";
                Toast::success("Emails queue processing completed successfully.")
                    ->autoHide(false);
            } else {
                $outputText .= "⚠️ Emails queue processing finished with exit code: {$exitCode}\n";
                Toast::warning("Emails queue processing finished with exit code: {$exitCode}")
                    ->autoHide(false);
            }
            
            $outputText .= "\n[$timestamp] Process completed.\n";
            
            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);
            
            Log::info("Manual emails queue processing - Exit code: {$exitCode}, Output: {$artisanOutput}");
            
        } catch (\Exception $e) {
            $errorOutput = "❌ Error processing emails queue: " . $e->getMessage() . "\n";
            $errorOutput .= "Stack trace:\n" . $e->getTraceAsString();
            
            session(['email_test_output' => $errorOutput]);
            
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
}
