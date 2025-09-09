<?php

namespace App\Mail;

use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        
        // Load template content from database
        $templateService = app(MailTemplateService::class);
        $this->templateData = $templateService->getTemplateContent('otp', [
            'user' => $user,
            'otp' => $otp,
            'app_name' => $this->appName,
        ]);
        
        // Queue this mail in the 'emails' queue instead of default
        $this->onQueue('emails');
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
            view: 'emails.template',
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