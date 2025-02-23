<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MailController extends Controller
{
    /// Dispatch Email Job (check SendEmailJob.php)
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
                    ->onQueue('emails');  // Optional: specify a queue name
    }



}
