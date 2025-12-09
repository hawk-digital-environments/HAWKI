<?php

namespace App\Services\Mail;

use App\Mail\TemplateMail;
use App\Models\MailTemplate;
use Illuminate\Support\Facades\Mail;

class MailService{

    public function sendWelcomeEmail($user)
    {
        // Get the welcome template (prefer German, fallback to English)
        $template = MailTemplate::where('type', 'welcome')
            ->where('language', 'de')
            ->first();

        if (!$template) {
            $template = MailTemplate::where('type', 'welcome')
                ->where('language', 'en')
                ->first();
        }

        if (!$template) {
            \Log::error("Welcome template not found in database");
            return false;
        }

        // Prepare template data
        $templateData = [
            '{{user_name}}' => $user->name,
        ];

        // Create and queue the email using TemplateMail
        $mail = TemplateMail::fromTemplate($template, $templateData, $user);
        Mail::to($user->email)->queue($mail);

        return true;
    }

}
