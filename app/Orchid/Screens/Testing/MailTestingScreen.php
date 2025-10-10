<?php

namespace App\Orchid\Screens\Testing;

use App\Mail\TemplateMail;
use App\Models\MailTemplate;
use App\Orchid\Layouts\Testing\TestingTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\MailTemplateService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MailTestingScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var MailTemplateService
     */
    private $mailTemplateService;

    /**
     * Construct the screen
     */
    public function __construct(SettingsService $settingsService, MailTemplateService $mailTemplateService)
    {
        $this->settingsService = $settingsService;
        $this->mailTemplateService = $mailTemplateService;
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Get combined jobs data for the table (pending + failed)
        $combinedJobsData = [];
        try {
            // Get pending jobs
            $jobs = \DB::table('jobs')
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();

            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? 'Unknown Job';

                $combinedJobsData[] = (object) [
                    'id' => $job->id,
                    'uuid' => null, // Pending jobs don't have UUID
                    'queue' => $job->queue,
                    'job_name' => $displayName,
                    'status' => 'pending',
                    'attempts' => $job->attempts,
                    'exception_preview' => null,
                    'exception_full' => null,
                    'connection' => null,
                    'available_at' => date('Y-m-d H:i:s', $job->available_at),
                    'created_at' => date('Y-m-d H:i:s', $job->created_at),
                    'failed_at' => null,
                ];
            }

            // Get failed jobs
            $failedJobs = \DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(25)
                ->get();

            foreach ($failedJobs as $failedJob) {
                $payload = json_decode($failedJob->payload, true);
                $displayName = $payload['displayName'] ?? 'Unknown Job';
                $exception = $failedJob->exception;
                // Extract first line of exception for preview
                $exceptionPreview = explode("\n", $exception)[0];
                $exceptionPreview = strlen($exceptionPreview) > 80 ? substr($exceptionPreview, 0, 80).'...' : $exceptionPreview;

                $combinedJobsData[] = (object) [
                    'id' => $failedJob->id,
                    'uuid' => $failedJob->uuid,
                    'queue' => $failedJob->queue,
                    'job_name' => $displayName,
                    'status' => 'failed',
                    'attempts' => null, // Failed jobs don't show attempts
                    'exception_preview' => $exceptionPreview,
                    'exception_full' => $exception,
                    'connection' => $failedJob->connection,
                    'available_at' => null,
                    'created_at' => null,
                    'failed_at' => date('Y-m-d H:i:s', strtotime($failedJob->failed_at)),
                ];
            }

            // Sort combined data by most recent (either created_at or failed_at)
            usort($combinedJobsData, function ($a, $b) {
                $timeA = $a->status === 'pending' ? strtotime($a->created_at) : strtotime($a->failed_at);
                $timeB = $b->status === 'pending' ? strtotime($b->created_at) : strtotime($b->failed_at);

                return $timeB - $timeA; // Descending order
            });

        } catch (\Exception $e) {
            // If there's an error, combinedJobsData will remain an empty array
        }

        return [
            'combinedJobsData' => $combinedJobsData,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Mail Testing';
    }

    public function description(): ?string
    {
        return 'Test email functionality, manage email queues, and debug email delivery.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
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
            ...$this->buildEmailTestingLayout(),
        ];
    }

    /**
     * Build layout for email testing
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildEmailTestingLayout()
    {
        return [
            // Layout::rows([
            //    Label::make('email_test_label')
            //        ->title('Basic Email Testing')
            //        ->help('Test basic email functionality and configuration')
            //        ->addclass('fw-bold'),
            //
            //    Group::make([
            //        Button::make('Send Test Email')
            //            ->icon('bs.envelope-check')
            //            ->method('sendTestMail')
            //            ->confirm('Send a simple test email to verify mail configuration?'),
            //
            //        Button::make('Send Welcome Email (Sync)')
            //            ->icon('bs.envelope')
            //            ->method('sendWelcomeEmailTestSync')
            //            ->confirm('This will send a welcome email synchronously (bypassing queue). Continue?'),
            //
            //        Button::make('Send Welcome Email (Queue)')
            //            ->icon('bs.envelope-plus')
            //            ->method('sendWelcomeEmailTest')
            //            ->confirm('This will queue a welcome email (requires queue worker). Continue?'),
            //
            //        Button::make('Send OTP Email')
            //            ->icon('bs.shield')
            //            ->method('sendOtpEmailTest')
            //            ->confirm('This will send an OTP (One-Time Password) email to the current authenticated user. Continue?'),
            //    ])->autoWidth(),
            //
            // ])->title('Legacy Email Testing'),

            Layout::rows([
                Label::make('template_test_label')
                    ->title('Template-Based Email Testing')
                    ->help('Test modern email templates with placeholder replacement using {{app_name}}, {{user_name}}, etc.')
                    ->addclass('fw-bold'),

                Select::make('template_type')
                    ->title('Select Template Type')
                    ->options([
                        'welcome' => 'Welcome Email Template',
                        'otp' => 'OTP Authentication Template',
                        'invitation' => 'Group Chat Invitation Template',
                        'notification' => 'General Notification Template',
                        'approval' => 'Account Approval Template',
                    ])
                    ->help('Choose which email template to test')
                    ->value('welcome'),

                Group::make([
                    Button::make('Send Template Email (Sync)')
                        ->icon('bs.envelope-at')
                        ->method('sendTemplateEmailTest')
                        ->confirm('Send a template-based email with placeholder replacement (synchronous)?'),

                    Button::make('Send Template Email (Queue)')
                        ->icon('bs.envelope-at-fill')
                        ->method('sendTemplateEmailTestQueued')
                        ->confirm('Queue a template-based email with placeholder replacement?'),

                    Button::make('Preview Template')
                        ->icon('bs.eye')
                        ->method('previewTemplate')
                        ->confirm('Preview the selected template with placeholder replacement in debug output?'),
                ])->autoWidth(),

            ])->title('Template Testing'),

            Layout::rows([
                Label::make('template_management_label')
                    ->title('Template Management')
                    ->help('Reset mail templates to default values with modern {{app_name}} placeholders')
                    ->addclass('fw-bold'),

                Group::make([
                    Button::make('Reset Selected Template')
                        ->icon('bs.arrow-clockwise')
                        ->method('resetSelectedTemplate')
                        ->confirm('Reset the selected template type to default values from seeder?'),

                    Button::make('Reset All Templates')
                        ->icon('bs.arrow-clockwise-square')
                        ->method('resetAllTemplates')
                        ->confirm('Reset ALL mail templates to default values from seeder? This will overwrite all customizations!'),
                ])->autoWidth(),

            ])->title('Template Management'),

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

                    Button::make('Process Mails Queue')
                        ->icon('bs.envelope-arrow-up')
                        ->method('processMailQueueManually')
                        ->confirm('Manually process pending mails queue jobs only?'),

                    Button::make('Process All Queues')
                        ->icon('bs.play-circle')
                        ->method('processQueueManually')
                        ->confirm('Manually process pending queue jobs from all queues (mails & default)?'),

                    Button::make('Clear Mails Queue')
                        ->icon('bs.trash')
                        ->method('clearEmailsQueue')
                        ->confirm('⚠️ This will DELETE all pending jobs in the mails queue. This action cannot be undone! Continue?'),

                    Button::make('Clear All Queues')
                        ->icon('bs.trash3')
                        ->method('clearAllQueues')
                        ->confirm('⚠️ This will DELETE all pending jobs in ALL queues (mails & default). This action cannot be undone! Continue?'),
                ])->autoWidth(),
            ])->title('Queue Management'),

            Layout::rows([
                // Debug/Output area
                Code::make('email_test_output')
                    ->language('text')
                    ->readonly()
                    ->value(session('email_test_output', 'No output yet. Run a queue operation to see results here.'))
                    ->height('350px')
                    ->help('Email test output and debug information will appear here'),
            ])->title('Debug Output'),

            $this->buildCombinedJobsListLayout(),
        ];
    }

    /**
     * Build layout for combined jobs list from database (pending + failed)
     *
     * @return \Orchid\Screen\Layout
     */
    private function buildCombinedJobsListLayout()
    {
        try {
            $combinedJobsData = $this->query()['combinedJobsData'];

            // If no jobs, show empty message
            if (empty($combinedJobsData)) {
                return Layout::rows([
                    Label::make('no_jobs')
                        ->title('No jobs in database')
                        ->help('No pending or failed jobs found in the system.')
                        ->addclass('text-center text-muted'),
                ])->title('Jobs Queue');
            }

            return Layout::table('combinedJobsData', [
                TD::make('id', 'ID')
                    ->sort()
                    ->width('60px')
                    ->render(function ($job) {
                        return $job->id;
                    }),

                TD::make('status', 'Status')
                    ->sort()
                    ->width('80px')
                    ->render(function ($job) {
                        $badgeClass = match ($job->status) {
                            'pending' => 'badge bg-warning',
                            'failed' => 'badge bg-danger',
                            default => 'badge bg-secondary'
                        };

                        return "<span class='{$badgeClass}'>".ucfirst($job->status).'</span>';
                    }),

                TD::make('queue', 'Queue')
                    ->sort()
                    ->width('100px')
                    ->render(function ($job) {
                        $badgeClass = match ($job->queue) {
                            'mails' => $job->status === 'failed' ? 'badge bg-primary' : 'badge bg-primary',
                            'default' => $job->status === 'failed' ? 'badge bg-outline-dark' : 'badge bg-secondary',
                            default => 'badge bg-info'
                        };

                        return "<span class='{$badgeClass}'>{$job->queue}</span>";
                    }),

                TD::make('job_name', 'Job Name')
                    ->sort()
                    ->width('200px')
                    ->render(function ($job) {
                        return $job->job_name;
                    }),

                TD::make('details', 'Details')
                    ->render(function ($job) {
                        if ($job->status === 'pending') {
                            $badgeClass = $job->attempts > 0 ? 'badge bg-warning' : 'badge bg-success';

                            return "<span class='{$badgeClass}'>Attempts: {$job->attempts}</span>";
                        } else {
                            return "<span class='text-danger small'>{$job->exception_preview}</span>";
                        }
                    }),

                TD::make('timestamp', 'Timestamp')
                    ->sort()
                    ->width('150px')
                    ->render(function ($job) {
                        if ($job->status === 'pending') {
                            return "<div><small class='text-muted'>Available:</small><br>{$job->available_at}</div>";
                        } else {
                            return "<div><small class='text-muted'>Failed:</small><br>{$job->failed_at}</div>";
                        }
                    }),

                TD::make('actions', 'Actions')
                    ->width('150px')
                    ->render(function ($job) {
                        if ($job->status === 'pending') {
                            // Actions for pending jobs (limited options)
                            return Group::make([
                                Button::make('View')
                                    ->icon('bs.eye')
                                    ->method('viewJobDetails', ['id' => $job->id, 'type' => 'pending'])
                                    ->class('btn btn-sm btn-outline-info'),
                            ])->autoWidth();
                        } else {
                            // Actions for failed jobs (full options)
                            return Group::make([
                                Button::make('View')
                                    ->icon('bs.eye')
                                    ->method('viewFailedJobException', ['uuid' => $job->uuid])
                                    ->class('btn btn-sm btn-outline-info'),

                                Button::make('Delete')
                                    ->icon('bs.trash')
                                    ->method('deleteFailedJob', ['id' => $job->id])
                                    ->confirm("Delete failed job '{$job->job_name}'? This cannot be undone.")
                                    ->class('btn btn-sm btn-outline-danger'),
                            ])->autoWidth();
                        }
                    }),
            ])->title('Jobs Queue - Last 50 Jobs');

        } catch (\Exception $e) {
            return Layout::rows([
                Label::make('jobs_error')
                    ->title('Error loading jobs from database')
                    ->help('Error: '.$e->getMessage())
                    ->addclass('text-danger'),
            ])->title('Jobs Queue Error');
        }
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

            if (! $user || ! $user->email) {
                Toast::error('No authenticated user with email address found.');

                return back();
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
            Log::error('Error sending test email: '.$e->getMessage());
            Toast::error('Failed to send test email: '.$e->getMessage());
        }

        return back();
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

            if (! $user) {
                Toast::error('No authenticated user found for email test.');

                return back();
            }

            if (! $user->email) {
                Toast::error('No email address found for current user.');

                return back();
            }

            // Use the MailController's sendWelcomeEmail method (this will queue the email)
            $mailController = new \App\Http\Controllers\MailController;
            $mailController->sendWelcomeEmail($user);

            Toast::info("Welcome email has been queued for: {$user->email}. Check queue status to see if it's processed.")
                ->autoHide(false);

            Log::info("Welcome email test (queued) for user: {$user->username} ({$user->email})");

        } catch (\Exception $e) {
            Log::error('Error queuing welcome email test: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());
            Toast::error('Failed to queue welcome email: '.$e->getMessage());
        }

        return back();
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

            if (! $user) {
                Toast::error('No authenticated user found for email test.');

                return back();
            }

            if (! $user->email) {
                Toast::error('No email address found for current user.');

                return back();
            }

            // Send welcome email synchronously (bypass queue)
            \Illuminate\Support\Facades\Mail::to($user->email)->sendNow(new \App\Mail\WelcomeMail($user));

            Toast::success("Welcome email has been sent synchronously to: {$user->email}")
                ->autoHide(false);

            Log::info("Welcome email test (sync) sent to user: {$user->username} ({$user->email})");

        } catch (\Exception $e) {
            Log::error('Error sending welcome email test (sync): '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());
            Toast::error('Failed to send welcome email (sync): '.$e->getMessage());
        }

        return back();
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

            if (! $user) {
                Toast::error('No authenticated user found for OTP email test.');

                return back();
            }

            // Create user info array similar to what's used in the handshake process
            $userInfo = [
                'username' => $user->username ?? $user->name,
                'email' => $user->email,
                'name' => $user->name ?? $user->username,
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
            Log::error('Error sending OTP email test: '.$e->getMessage());
            Toast::error('Failed to send OTP email: '.$e->getMessage());
        }

        return back();
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
            $outputText .= str_repeat('=', 50)."\n\n";

            // Get queue connection info
            $queueConnection = config('queue.default');
            $outputText .= "Queue Connection: {$queueConnection}\n\n";

            // Check if we can get queue size (works for database queue)
            $defaultPendingJobs = 'Unknown';
            $mailsPendingJobs = 'Unknown';
            if ($queueConnection === 'database') {
                try {
                    $defaultPendingJobs = \DB::table('jobs')->where('queue', 'default')->count();
                    $mailsPendingJobs = \DB::table('jobs')->where('queue', 'mails')->count();
                } catch (\Exception $e) {
                    $defaultPendingJobs = 'Error: '.$e->getMessage();
                    $mailsPendingJobs = 'Error: '.$e->getMessage();
                }
            }

            // Try to get failed jobs count
            $failedJobs = 'Unknown';
            try {
                $failedJobs = \DB::table('failed_jobs')->count();
            } catch (\Exception $e) {
                $failedJobs = 'Error: '.$e->getMessage();
            }

            $outputText .= "📊 Queue Statistics:\n";
            $outputText .= "  • Default Queue Jobs: {$defaultPendingJobs}\n";
            $outputText .= "  • Mails Queue Jobs: {$mailsPendingJobs}\n";
            $outputText .= "  • Failed Jobs: {$failedJobs}\n\n";

            // Add configuration details
            $outputText .= "⚙️ Configuration:\n";
            $outputText .= '  • Queue Driver: '.config('queue.connections.'.$queueConnection.'.driver', 'N/A')."\n";
            $outputText .= '  • Queue Table: '.config('queue.connections.'.$queueConnection.'.table', 'N/A')."\n\n";

            $outputText .= "[$timestamp] Status check completed.\n";

            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);

            $message = "Default Queue Jobs: {$defaultPendingJobs}, Mails Queue Jobs: {$mailsPendingJobs}, Failed Jobs: {$failedJobs}";
            Toast::info($message)->autoHide(false);
            Log::info("Queue status check: {$message}");

        } catch (\Exception $e) {
            $errorOutput = '❌ Error checking queue status: '.$e->getMessage()."\n";
            $errorOutput .= "Stack trace:\n".$e->getTraceAsString();

            session(['email_test_output' => $errorOutput]);

            Log::error('Error checking queue status: '.$e->getMessage());
            Toast::error('Failed to check queue status: '.$e->getMessage());
        }

        return back();
    }

    /**
     * View details for a pending job
     *
     * @return void
     */
    public function viewJobDetails(Request $request)
    {
        try {
            $id = $request->get('id');
            $type = $request->get('type');

            if ($type === 'pending') {
                $job = \DB::table('jobs')->where('id', $id)->first();

                if (! $job) {
                    Toast::error('Job not found.');

                    return;
                }

                $payload = json_decode($job->payload, true);

                // Store job details in session for display in codebox
                $timestamp = now()->format('Y-m-d H:i:s');
                $outputText = "[$timestamp] Pending Job Details\n";
                $outputText .= str_repeat('=', 60)."\n\n";
                $outputText .= "Job ID: {$job->id}\n";
                $outputText .= "Queue: {$job->queue}\n";
                $outputText .= "Attempts: {$job->attempts}\n";
                $outputText .= 'Available At: '.date('Y-m-d H:i:s', $job->available_at)."\n";
                $outputText .= 'Created At: '.date('Y-m-d H:i:s', $job->created_at)."\n\n";
                $outputText .= "Payload:\n";
                $outputText .= str_repeat('-', 40)."\n";
                $outputText .= json_encode($payload, JSON_PRETTY_PRINT);
                $outputText .= "\n".str_repeat('-', 40)."\n";
                $outputText .= "\n[$timestamp] Job details displayed.\n";

                session(['email_test_output' => $outputText]);

                Toast::info('Job details displayed in debug output.');
            }

        } catch (\Exception $e) {
            Log::error('Error viewing job details: '.$e->getMessage());
            Toast::error('Failed to view job details: '.$e->getMessage());
        }

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
            $outputText = "[$timestamp] Starting queue processing (mails & default)...\n\n";

            // Process mails queue first
            $emailsExitCode = Artisan::call('queue:work', [
                '--queue' => 'mails,default',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3,
            ]);

            $artisanOutput = Artisan::output();
            $outputText .= "Exit Code: {$emailsExitCode}\n";
            $outputText .= "Command Output:\n";
            $outputText .= str_repeat('-', 50)."\n";
            $outputText .= $artisanOutput;
            $outputText .= "\n".str_repeat('-', 50)."\n";

            if ($emailsExitCode === 0) {
                $outputText .= "✅ Queue processing completed successfully!\n";
                Toast::success('Queue processing completed successfully (mails & default queues).')
                    ->autoHide(false);
            } else {
                $outputText .= "⚠️ Queue processing finished with exit code: {$emailsExitCode}\n";
                Toast::warning("Queue processing finished with exit code: {$emailsExitCode}")
                    ->autoHide(false);
            }

            $outputText .= "\n[$timestamp] Process completed.\n";

            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);

            Log::info("Manual queue processing (mails & default) - Exit code: {$emailsExitCode}, Output: {$artisanOutput}");

        } catch (\Exception $e) {
            $errorOutput = '❌ Error processing queue: '.$e->getMessage()."\n";
            $errorOutput .= "Stack trace:\n".$e->getTraceAsString();

            session(['email_test_output' => $errorOutput]);

            Log::error('Error processing queue manually: '.$e->getMessage());
            Toast::error('Failed to process queue: '.$e->getMessage());
        }

        return back();
    }

    /**
     * Manually process mails queue jobs only
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processMailQueueManually(Request $request)
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Starting mails queue processing...\n\n";

            // Process only mails queue
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'mails',
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 3,
            ]);

            $artisanOutput = Artisan::output();
            $outputText .= "Exit Code: {$exitCode}\n";
            $outputText .= "Command Output:\n";
            $outputText .= str_repeat('-', 50)."\n";
            $outputText .= $artisanOutput;
            $outputText .= "\n".str_repeat('-', 50)."\n";

            if ($exitCode === 0) {
                $outputText .= "✅ Mails queue processing completed successfully!\n";
                Toast::success('Mails queue processing completed successfully.')
                    ->autoHide(false);
            } else {
                $outputText .= "⚠️ Mails queue processing finished with exit code: {$exitCode}\n";
                Toast::warning("Mails queue processing finished with exit code: {$exitCode}")
                    ->autoHide(false);
            }

            $outputText .= "\n[$timestamp] Process completed.\n";

            // Store output in session to display in codebox
            session(['email_test_output' => $outputText]);

            Log::info("Manual mails queue processing - Exit code: {$exitCode}, Output: {$artisanOutput}");

        } catch (\Exception $e) {
            $errorOutput = '❌ Error processing mails queue: '.$e->getMessage()."\n";
            $errorOutput .= "Stack trace:\n".$e->getTraceAsString();

            session(['email_test_output' => $errorOutput]);

            Log::error('Error processing mails queue manually: '.$e->getMessage());
            Toast::error('Failed to process mails queue: '.$e->getMessage());
        }

        return back();
    }

    /**
     * Clear all jobs from the mails queue
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
                $deletedJobs = \DB::table('jobs')->where('queue', 'mails')->count();
                \DB::table('jobs')->where('queue', 'mails')->delete();

                Toast::success("Successfully cleared mails queue. Deleted {$deletedJobs} job(s).")
                    ->autoHide(false);

                Log::info("Cleared mails queue - Deleted {$deletedJobs} jobs");
            } else {
                // For other queue types, use Artisan command
                $exitCode = Artisan::call('queue:clear', ['--queue' => 'mails']);
                $output = Artisan::output();

                if ($exitCode === 0) {
                    Toast::success("Mails queue cleared successfully.\nOutput: ".$output)
                        ->autoHide(false);
                } else {
                    Toast::warning("Queue clear finished with exit code: {$exitCode}\nOutput: ".$output)
                        ->autoHide(false);
                }

                Log::info("Cleared mails queue via Artisan - Exit code: {$exitCode}, Output: {$output}");
            }

        } catch (\Exception $e) {
            Log::error('Error clearing mails queue: '.$e->getMessage());
            Toast::error('Failed to clear mails queue: '.$e->getMessage());
        }

        return back();
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
                    Toast::success("All queues cleared successfully.\nOutput: ".$output)
                        ->autoHide(false);
                } else {
                    Toast::warning("Queue clear finished with exit code: {$exitCode}\nOutput: ".$output)
                        ->autoHide(false);
                }

                Log::info("Cleared all queues via Artisan - Exit code: {$exitCode}, Output: {$output}");
            }

        } catch (\Exception $e) {
            Log::error('Error clearing all queues: '.$e->getMessage());
            Toast::error('Failed to clear all queues: '.$e->getMessage());
        }

        return back();
    }

    /**
     * View full exception details for a failed job
     *
     * @return void
     */
    public function viewFailedJobException(Request $request)
    {
        try {
            $uuid = $request->get('uuid');
            $failedJob = \DB::table('failed_jobs')->where('uuid', $uuid)->first();

            if (! $failedJob) {
                Toast::error('Failed job not found.');

                return;
            }

            // Parse the payload to show job configuration
            $payload = json_decode($failedJob->payload, true);

            // Store the full exception in session for display in codebox
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Failed Job Exception Details\n";
            $outputText .= str_repeat('=', 60)."\n\n";
            $outputText .= "Job UUID: {$failedJob->uuid}\n";
            $outputText .= "Queue: {$failedJob->queue}\n";
            $outputText .= "Connection: {$failedJob->connection}\n";
            $outputText .= "Failed At: {$failedJob->failed_at}\n\n";

            // Show job payload information
            $outputText .= "Job Configuration (Stored in Payload):\n";
            $outputText .= str_repeat('-', 40)."\n";
            if (isset($payload['displayName'])) {
                $outputText .= "Job Type: {$payload['displayName']}\n";
            }
            if (isset($payload['data'])) {
                $outputText .= 'Job Data: '.json_encode($payload['data'], JSON_PRETTY_PRINT)."\n";
            }
            $outputText .= "\n⚠️  IMPORTANT NOTICE:\n";
            $outputText .= "This job contains the configuration that was active when it was originally queued.\n";
            $outputText .= "If you have changed mail settings since then, consider deleting this failed job and creating a new one.\n\n";

            $outputText .= "Full Exception:\n";
            $outputText .= str_repeat('-', 40)."\n";
            $outputText .= $failedJob->exception;
            $outputText .= "\n".str_repeat('-', 40)."\n";
            $outputText .= "\n[$timestamp] Exception details displayed.\n";

            session(['email_test_output' => $outputText]);

            Toast::info('Exception details displayed in debug output.')
                ->autoHide(false);

        } catch (\Exception $e) {
            Log::error('Error viewing failed job exception: '.$e->getMessage());
            Toast::error('Failed to view job exception: '.$e->getMessage());
        }

    }

    /**
     * Delete a failed job
     *
     * @return void
     */
    public function deleteFailedJob(Request $request)
    {
        try {
            $id = $request->get('id');

            $failedJob = \DB::table('failed_jobs')->where('id', $id)->first();
            if (! $failedJob) {
                Toast::error('Failed job not found.');

                return;
            }

            // Delete the failed job
            $deleted = \DB::table('failed_jobs')->where('id', $id)->delete();

            if ($deleted) {
                Toast::success('Failed job has been deleted successfully.');
                Log::info("Failed job ID {$id} deleted successfully.");
            } else {
                Toast::error('Failed to delete job.');
                Log::error("Failed to delete failed job ID {$id}.");
            }

        } catch (\Exception $e) {
            Log::error('Error deleting failed job: '.$e->getMessage());
            Toast::error('Failed to delete job: '.$e->getMessage());
        }

    }

    /**
     * Send a template-based email test (synchronous)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTemplateEmailTest(Request $request)
    {
        try {
            $user = Auth::user();
            $templateType = $request->get('template_type', 'welcome');

            if (! $user || ! $user->email) {
                Toast::error('No authenticated user with email address found.');

                return back();
            }

            // Get template using German preference, fallback to English
            $template = MailTemplate::where('type', $templateType)
                ->where('language', 'de')
                ->first();

            if (! $template) {
                $template = MailTemplate::where('type', $templateType)
                    ->where('language', 'en')
                    ->first();
            }

            if (! $template) {
                Toast::error("Template '{$templateType}' not found in database. Try resetting templates first.");

                return back();
            }

            // Get template-specific test data with placeholders
            $testData = $this->getTemplateTestData($templateType, $user);

            // Create and send the email using TemplateMail
            $mail = TemplateMail::fromTemplate($template, $testData, $user);

            // Add [TEST] prefix to subject for clarity
            $mail->subject = '[TEST-SYNC] '.$mail->subject;

            Mail::to($user->email)->sendNow($mail);

            $placeholderInfo = implode(', ', array_keys($testData));
            Toast::success("Template email '{$templateType}' sent synchronously to: {$user->email} (Language: {$template->language}, Placeholders: {$placeholderInfo})")
                ->autoHide(false);

            Log::info('Template email test (sync) sent', [
                'template_type' => $templateType,
                'template_language' => $template->language,
                'recipient' => $user->email,
                'placeholders' => array_keys($testData),
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending template email test (sync): '.$e->getMessage());
            Toast::error('Failed to send template email (sync): '.$e->getMessage());
        }

        return back();
    }

    /**
     * Send a template-based email test (queued)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTemplateEmailTestQueued(Request $request)
    {
        try {
            $user = Auth::user();
            $templateType = $request->get('template_type', 'welcome');

            if (! $user || ! $user->email) {
                Toast::error('No authenticated user with email address found.');

                return back();
            }

            // Get template using German preference, fallback to English
            $template = MailTemplate::where('type', $templateType)
                ->where('language', 'de')
                ->first();

            if (! $template) {
                $template = MailTemplate::where('type', $templateType)
                    ->where('language', 'en')
                    ->first();
            }

            if (! $template) {
                Toast::error("Template '{$templateType}' not found in database. Try resetting templates first.");

                return back();
            }

            // Get template-specific test data with placeholders
            $testData = $this->getTemplateTestData($templateType, $user);

            // Create and queue the email using TemplateMail
            $mail = TemplateMail::fromTemplate($template, $testData, $user);

            // Add [TEST] prefix to subject for clarity
            $mail->subject = '[TEST-QUEUE] '.$mail->subject;

            // Queue the mail (queue is already set to 'mails' in TemplateMail constructor)
            Mail::to($user->email)->queue($mail);

            $placeholderInfo = implode(', ', array_keys($testData));
            Toast::info("Template email '{$templateType}' queued for: {$user->email} (Language: {$template->language}, Placeholders: {$placeholderInfo}). Check queue status to see if it's processed.")
                ->autoHide(false);

            Log::info('Template email test (queued)', [
                'template_type' => $templateType,
                'template_language' => $template->language,
                'recipient' => $user->email,
                'placeholders' => array_keys($testData),
            ]);

        } catch (\Exception $e) {
            Log::error('Error queuing template email test: '.$e->getMessage());
            Toast::error('Failed to queue template email: '.$e->getMessage());
        }

        return back();
    }

    /**
     * Preview a template with placeholder replacement
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function previewTemplate(Request $request)
    {
        try {
            $user = Auth::user();
            $templateType = $request->get('template_type', 'welcome');

            if (! $user) {
                Toast::error('No authenticated user found.');

                return back();
            }

            // Get template content using the service
            $testData = $this->getTemplateTestData($templateType, $user);
            $templateContent = $this->mailTemplateService->getTemplateContent($templateType, $testData);

            // Get available placeholders info
            $placeholders = $this->mailTemplateService->getAvailablePlaceholders($templateType);

            // Generate preview output
            $timestamp = now()->format('Y-m-d H:i:s');
            $outputText = "[$timestamp] Template Preview: '{$templateType}'\n";
            $outputText .= str_repeat('=', 60)."\n\n";

            $outputText .= "📧 EMAIL PREVIEW:\n";
            $outputText .= str_repeat('-', 40)."\n";
            $outputText .= "Subject: {$templateContent['subject']}\n\n";
            $outputText .= "Body (HTML):\n";
            $outputText .= $templateContent['body']."\n";
            $outputText .= str_repeat('-', 40)."\n\n";

            $outputText .= "🔤 PLACEHOLDER REPLACEMENTS:\n";
            foreach ($testData as $key => $value) {
                $outputText .= "  • {{{$key}}} → {$value}\n";
            }
            $outputText .= "\n";

            $outputText .= "📋 AVAILABLE PLACEHOLDERS FOR '{$templateType}' TEMPLATES:\n";
            foreach ($placeholders as $placeholder => $description) {
                $outputText .= "  • {{{$placeholder}}} - {$description}\n";
            }

            $outputText .= "\n[$timestamp] Template preview completed.\n";

            // Store output in session for display in codebox
            session(['email_test_output' => $outputText]);

            Toast::info("Template '{$templateType}' preview displayed in debug output with placeholder replacements.")
                ->autoHide(false);

        } catch (\Exception $e) {
            Log::error('Error previewing template: '.$e->getMessage());
            Toast::error('Failed to preview template: '.$e->getMessage());
        }

        return back();
    }

    /**
     * Reset selected template to default values
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetSelectedTemplate(Request $request)
    {
        $templateType = $request->get('template_type', 'welcome');

        if (! $templateType) {
            Toast::error('Template type not found.');

            return back();
        }

        try {
            $result = $this->mailTemplateService->resetTemplates($templateType);

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    Toast::error($error);
                }

                return back();
            }

            Toast::success("Mail template '{$templateType}' has been reset to default values ({$result['reset_count']} templates updated).");

            Log::info('Mail template reset from testing screen', [
                'template_type' => $templateType,
                'reset_count' => $result['reset_count'],
                'reset_to_default' => true,
            ]);

            return back();

        } catch (\Exception $e) {
            Log::error('Error resetting mail template: '.$e->getMessage());
            Toast::error('Error resetting mail template: '.$e->getMessage());

            return back();
        }
    }

    /**
     * Reset all mail templates to default values
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetAllTemplates()
    {
        try {
            $result = $this->mailTemplateService->resetTemplates(); // null = all templates

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    Toast::error($error);
                }

                return back();
            }

            Toast::success("All mail templates have been reset to default values ({$result['reset_count']} templates updated).");

            Log::info('All mail templates reset from testing screen', [
                'reset_count' => $result['reset_count'],
                'template_types' => $result['template_types'],
                'reset_to_default' => true,
            ]);

            return back();

        } catch (\Exception $e) {
            Log::error('Error resetting all mail templates: '.$e->getMessage());
            Toast::error('Error resetting all mail templates: '.$e->getMessage());

            return back();
        }
    }

    /**
     * Get template-specific test data for placeholder replacement
     */
    private function getTemplateTestData(string $templateType, $user): array
    {
        $baseData = [
            'app_name' => config('app.name', 'HAWKI'),
            'user_name' => $user->name ?? $user->username ?? 'Test User',
            'user_email' => $user->email ?? 'test@example.com',
            'app_url' => config('app.url', 'https://hawki.test'),
            'current_date' => now()->format('Y-m-d'),
            'current_datetime' => now()->format('Y-m-d H:i:s'),
        ];

        // Add template-specific test data
        return match ($templateType) {
            'otp' => array_merge($baseData, [
                'otp' => '123456',
            ]),
            'invitation' => array_merge($baseData, [
                'room_name' => 'Test Group Chat Room',
                'inviter_name' => 'Test Inviter',
                'invitation_url' => config('app.url').'/chat/join/test-room',
            ]),
            default => $baseData,
        };
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
