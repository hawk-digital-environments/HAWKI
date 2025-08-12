<?php

namespace App\Mail;

use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $inviter;
    public $roomName;
    public $invitationUrl;
    public $templateData;

    public function __construct($user, $inviter, $roomName, $invitationUrl)
    {
        $this->user = $user;
        $this->inviter = $inviter;
        $this->roomName = $roomName;
        $this->invitationUrl = $invitationUrl;
        
        // Load template content from database
        $templateService = app(MailTemplateService::class);
        $this->templateData = $templateService->getTemplateContent('invitation', [
            'user' => $user,
            'inviter_name' => $inviter->username ?? $inviter->name ?? 'Ein Benutzer',
            'room_name' => $roomName,
            'invitation_url' => $invitationUrl,
        ]);
        
        // Queue this mail in the 'emails' queue instead of default
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->user->email,
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
                'inviter' => $this->inviter,
                'roomName' => $this->roomName,
                'invitationUrl' => $this->invitationUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
