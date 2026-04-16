<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    // / Send Welcome Email using the WelcomeMail Mailable
    public function sendWelcomeEmail($user)
    {
        try {
            // Send welcome email using the WelcomeMail mailable
            Mail::to($user->email)->send(new WelcomeMail($user));

            Log::info('Welcome email sent successfully to: '.$user->email);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: '.$e->getMessage());
            throw $e;
        }
    }

    private function isHerdEnvironment(): bool
    {
        return str_contains(gethostname(), '.herd') ||
               str_contains($_SERVER['SERVER_NAME'] ?? '', '.test');
    }
}
