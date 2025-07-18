<?php

namespace App\Mail;

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

    public function __construct($user, $otp, $appName = null)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->appName = $appName ?? config('app.name', 'HAWKI');
        // Queue this mail in the 'emails' queue instead of default
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->appName . ' Log-In Code: ' . $this->otp,
            from: config('mail.from.address', 'noreply@hawki.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}