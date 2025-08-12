<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\MailTemplateService;

class PreviewMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $emailSubject;
    protected string $emailBody;
    protected array $templateData;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string $body
     * @param array $data
     */
    public function __construct(string $subject, string $body, array $data)
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->templateData = $data;
    }

    /**
     * Build the message.
     *
     * @return PreviewMail
     */
    public function build(): PreviewMail
    {
        $templateService = app(MailTemplateService::class);
        
        // Replace placeholders in subject and body
        $processedSubject = $templateService->replacePlaceholders($this->emailSubject, $this->templateData);
        $processedBody = $templateService->replacePlaceholders($this->emailBody, $this->templateData);
        
        return $this->subject('[VORSCHAU] ' . $processedSubject)
                    ->html($processedBody);
    }
}
