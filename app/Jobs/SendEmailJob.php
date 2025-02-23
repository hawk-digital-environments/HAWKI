<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\GeneralMail;
use Illuminate\Support\Facades\Log;
use Exception;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $emailData;
    public $recipient;
    public $subjectLine;
    public $viewTemplate;

    /**
     * Create a new job instance.
     *
     * @param array $emailData The data to be passed to the email.
     * @param string $recipient The recipient's email address.
     * @param string $subjectLine The subject of the email.
     * @param string|null $viewTemplate The view template for the email.
     */
    public function __construct($emailData, $recipient, $subjectLine, $viewTemplate)
    {
        $this->emailData = $emailData;
        $this->recipient = $recipient;
        $this->subjectLine = $subjectLine;
        $this->viewTemplate = $viewTemplate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $mailData = new GeneralMail($this->emailData, $this->subjectLine, $this->viewTemplate);
            Mail::to($this->recipient)->send($mailData);
        } catch (Exception $e) {
            Log::error('Error handling the job: ' . $e->getMessage());
            throw $e; // Optional: rethrow if you want it to be recorded in `failed_jobs`
        }
    }
}
