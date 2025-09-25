<?php

namespace App\Mail;

use App\Services\MailPlaceholderService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $templateSubject;

    protected $templateBody;

    protected $customData;

    protected $user;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User|null  $user
     */
    public function __construct(string $subject, string $body, array $customData = [], $user = null)
    {
        $this->templateSubject = $subject;
        $this->templateBody = $body;
        $this->customData = $customData;
        $this->user = $user;

        // Set default queue to 'mails' for HAWKI standard
        $this->onQueue('mails');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Get user placeholders
        $userPlaceholders = MailPlaceholderService::getUserPlaceholders($this->user);

        // Merge all placeholder data
        $allPlaceholders = array_merge(
            MailPlaceholderService::getStandardPlaceholders(),
            $userPlaceholders,
            $this->customData
        );

        // Replace placeholders in subject and body
        $processedSubject = MailPlaceholderService::replacePlaceholders($this->templateSubject, $allPlaceholders);
        $processedBody = MailPlaceholderService::replacePlaceholders($this->templateBody, $allPlaceholders);

        return $this->subject($processedSubject)
            ->html($processedBody);
    }

    /**
     * Create mail from template
     *
     * @param  \App\Models\MailTemplate  $template
     * @param  \App\Models\User|null  $user
     * @return static
     */
    public static function fromTemplate($template, array $customData = [], $user = null)
    {
        return new static($template->subject, $template->body, $customData, $user);
    }
}
