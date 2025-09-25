<?php

namespace App\Mail;

use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OTPMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $otp;

    public $appName;

    public $templateData;

    public function __construct($user, $otp, $appName = null)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->appName = $appName ?? config('app.name', 'HAWKI');

        // Try to load template content from database, fallback to defaults
        try {
            $templateService = app(MailTemplateService::class);
            $this->templateData = $templateService->getTemplateContent('otp', [
                'user' => $user,
                'otp' => $otp,
                'app_name' => $this->appName,
            ]);
        } catch (\Exception $e) {
            // Fallback to default template data if service fails
            Log::warning('MailTemplateService failed, using default OTP template', [
                'error' => $e->getMessage(),
            ]);

            $this->templateData = [
                'subject' => 'Your '.$this->appName.' OTP Code',
                'content' => null, // Will use default template in view
            ];
        }

        // Queue this mail in the 'mails' queue instead of default
        $this->onQueue('mails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->templateData['subject'],
            from: config('mail.from.address', 'noreply@hawki.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'templateData' => $this->templateData,
                'user' => $this->user,
                'otp' => $this->otp,
                'appName' => $this->appName,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
