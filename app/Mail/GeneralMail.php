<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GeneralMail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailData;
    public $subjectLine;
    public $viewTemplate;

    /**
     * Create a new message instance.
     *
     * @param array $emailData The data to be passed to the email.
     * @param string $subjectLine The subject of the email.
     * @param string|null $viewTemplate The view template for the email.
     */
    public function __construct($emailData, $subjectLine, $viewTemplate)
    {
        $this->emailData = $emailData;
        $this->subjectLine = $subjectLine;
        $this->viewTemplate = $viewTemplate;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subjectLine)
                    ->view($this->viewTemplate)
                    ->with('emailData', $this->emailData);
    }
}
