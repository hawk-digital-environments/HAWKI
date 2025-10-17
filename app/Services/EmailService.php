<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send an email using a template
     */
    public function sendTemplatedEmail(
        string $templateType,
        string $recipientEmail,
        array $customData = [],
        ?User $user = null,
        string $language = 'de'
    ): bool {
        try {
            // Get the template
            $template = MailTemplate::where('type', $templateType)
                ->where('language', $language)
                ->first();

            if (! $template) {
                // Fallback to English if German not found
                $template = MailTemplate::where('type', $templateType)
                    ->where('language', 'en')
                    ->first();
            }

            if (! $template) {
                throw new \Exception("Template '{$templateType}' not found");
            }

            // Create and send the email
            $mail = TemplateMail::fromTemplate($template, $customData, $user);
            Mail::to($recipientEmail)->send($mail);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send templated email: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send welcome email to a new user
     */
    public function sendWelcomeEmail(User $user, array $customData = []): bool
    {
        return $this->sendTemplatedEmail('welcome', $user->email, $customData, $user);
    }

    /**
     * Send OTP email
     */
    public function sendOtpEmail(User $user, string $otpCode): bool
    {
        $customData = ['{{otp_code}}' => $otpCode];

        return $this->sendTemplatedEmail('otp', $user->email, $customData, $user);
    }

    /**
     * Send invitation email
     */
    public function sendInvitationEmail(
        string $recipientEmail,
        string $invitationLink,
        string $roomName,
        User $inviter
    ): bool {
        $customData = [
            '{{invitation_link}}' => $invitationLink,
            '{{room_name}}' => $roomName,
            '{{inviter_name}}' => $inviter->name,
        ];

        return $this->sendTemplatedEmail('invitation', $recipientEmail, $customData, $inviter);
    }

    /**
     * Send approval/registration confirmation email
     */
    public function sendApprovalEmail(User $user): bool
    {
        return $this->sendTemplatedEmail('approval', $user->email, [], $user);
    }

    /**
     * Send general notification email
     */
    public function sendNotificationEmail(User $user, array $customData = []): bool
    {
        return $this->sendTemplatedEmail('notification', $user->email, $customData, $user);
    }
}
