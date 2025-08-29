<?php

namespace App\Services\Mail;


use App\Jobs\SendEmailJob;


class MailService{


    public function sendWelcomeEmail($user)
    {
        $emailData = [
            'user' => $user,
            'message' => 'Welcome to our platform!',
        ];

        $subjectLine = 'Welcome to Our App!';
        $viewTemplate = 'emails.welcome';

        // Dispatch the email job to the queue
        SendEmailJob::dispatch($emailData, $user->email, $subjectLine, $viewTemplate)
                    ->onQueue('emails');
    }



}
