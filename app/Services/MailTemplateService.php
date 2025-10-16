<?php

namespace App\Services;

use App\Models\MailTemplate;
use Illuminate\Support\Facades\Log;

class MailTemplateService
{
    public function getTemplateContent(string $templateType, array $data = []): array
    {
        $template = MailTemplate::findByType($templateType);

        if (! $template) {
            Log::warning("Mail template not found: {$templateType}");

            return [
                'subject' => 'Template not found',
                'body' => '<p>Template not found: '.$templateType.'</p>',
            ];
        }

        $subject = $template->subject;
        $body = $template->body;

        if (! empty($data)) {
            foreach ($data as $key => $value) {
                $placeholder = '{{'.$key.'}}';
                $subject = str_replace($placeholder, $value, $subject);
                $body = str_replace($placeholder, $value, $body);
            }
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function getAvailablePlaceholders(string $templateType): array
    {
        $common = [
            'app_name' => 'Name of the application',
            'user_name' => 'Name des Benutzers',
            'user_email' => 'User\'s email address',
            'app_url' => 'URL of the application',
            'support_email' => 'Support email address',
            'current_date' => 'Current date',
            'current_datetime' => 'Current date and time',
        ];

        $specific = match ($templateType) {
            'welcome' => [],
            'otp' => ['otp' => 'One-time password code'],
            'invitation' => [
                'room_name' => 'Name of the chat room',
                'inviter_name' => 'Name of the person sending invitation',
                'invitation_url' => 'URL to accept invitation',
            ],
            'approval' => [],
            'approval_pending' => [],
            'notification' => [
                'notification_title' => 'Title of the notification',
                'notification_message' => 'Main notification message',
                'action_url' => 'URL for action button',
                'action_text' => 'Text for action button',
            ],
            default => [],
        };

        return array_merge($common, $specific);
    }

    public function getPlaceholderHelpText(string $templateType, string $fieldType = 'body'): string
    {
        $placeholders = $this->getAvailablePlaceholders($templateType);

        $helpText = ($fieldType === 'body' ? 'HTML template with syntax highlighting. ' : '').'Available placeholders:<br>';
        foreach ($placeholders as $placeholder => $description) {
            $displayPlaceholder = '{{'.$placeholder.'}}';
            $helpText .= "<code>{$displayPlaceholder}</code> - {$description}<br>";
        }

        return rtrim($helpText, '<br>');
    }

    /**
     * Reset mail template(s) to default values
     * Unified method for all reset operations
     */
    public function resetTemplates($templateTypes = null): array
    {
        $resetCount = 0;
        $errors = [];

        // If no specific types provided, reset all templates
        if ($templateTypes === null) {
            $templateTypes = ['welcome', 'otp', 'invitation', 'notification', 'approval', 'approval_pending'];
        } elseif (is_string($templateTypes)) {
            $templateTypes = [$templateTypes];
        }

        foreach ($templateTypes as $templateType) {
            try {
                // Get default content for both languages
                $deTemplate = $this->getDefaultTemplateContent($templateType, 'de');
                $enTemplate = $this->getDefaultTemplateContent($templateType, 'en');

                // Reset German template
                if (! empty($deTemplate['subject']) || ! empty($deTemplate['body'])) {
                    MailTemplate::updateOrCreate(
                        ['type' => $templateType, 'language' => 'de'],
                        [
                            'description' => $deTemplate['description'],
                            'subject' => $deTemplate['subject'],
                            'body' => $deTemplate['body'],
                        ]
                    );
                    $resetCount++;
                }

                // Reset English template
                if (! empty($enTemplate['subject']) || ! empty($enTemplate['body'])) {
                    MailTemplate::updateOrCreate(
                        ['type' => $templateType, 'language' => 'en'],
                        [
                            'description' => $enTemplate['description'],
                            'subject' => $enTemplate['subject'],
                            'body' => $enTemplate['body'],
                        ]
                    );
                    $resetCount++;
                }

            } catch (\Exception $e) {
                $errors[] = "Error resetting template '{$templateType}': ".$e->getMessage();
                Log::error("Error resetting mail template {$templateType}: ".$e->getMessage());
            }
        }

        return [
            'reset_count' => $resetCount,
            'errors' => $errors,
            'template_types' => $templateTypes,
        ];
    }

    public function getDefaultTemplateContent(string $templateType, string $language = 'en'): array
    {
        // Get default content from the same source as the seeder
        $defaultTemplates = $this->getSeederTemplateDefaults();
        $key = $templateType.'_'.$language;

        if (isset($defaultTemplates[$key])) {
            return [
                'description' => $this->getTemplateDescription($templateType),
                'subject' => $defaultTemplates[$key]['subject'],
                'body' => $defaultTemplates[$key]['body'],
            ];
        }

        // Fallback to old simple templates if not found
        return [
            'description' => $this->getTemplateDescription($templateType),
            'subject' => $this->getDefaultSubject($templateType),
            'body' => $this->getDefaultBody($templateType),
        ];
    }

    /**
     * Get template description - same as used in seeder
     */
    private function getTemplateDescription(string $templateType): string
    {
        return match ($templateType) {
            'welcome' => 'Welcome email for new users',
            'otp' => 'Authentication code email',
            'invitation' => 'Group chat invitation email',
            'notification' => 'General notification email',
            'approval' => 'Account approval confirmation email',
            'approval_pending' => 'Account pending approval notification',
            default => 'General email template for '.$templateType,
        };
    }

    /**
     * Get the same modern template defaults as used in the seeder
     * This method uses the seeder directly as Single Source of Truth
     */
    private function getSeederTemplateDefaults(): array
    {
        // Create seeder instance and get template data directly
        $seeder = new \Database\Seeders\MailTemplateSeeder;

        // Use reflection to access the seeder's template methods
        $reflectionClass = new \ReflectionClass($seeder);

        $templates = [];

        // Get templates using the seeder's methods directly
        $templateMethods = [
            'welcome_en' => 'getWelcomeTemplateEn',
            'welcome_de' => 'getWelcomeTemplateDe',
            'otp_en' => 'getOtpTemplateEn',
            'otp_de' => 'getOtpTemplateDe',
            'invitation_en' => 'getInvitationTemplateEn',
            'invitation_de' => 'getInvitationTemplateDe',
            'notification_en' => 'getNotificationTemplateEn',
            'notification_de' => 'getNotificationTemplateDe',
            'approval_en' => 'getApprovalTemplateEn',
            'approval_de' => 'getApprovalTemplateDe',
            'approval_pending_en' => 'getApprovalPendingTemplateEn',
            'approval_pending_de' => 'getApprovalPendingTemplateDe',
        ];

        foreach ($templateMethods as $key => $methodName) {
            try {
                $method = $reflectionClass->getMethod($methodName);
                $method->setAccessible(true);
                $body = $method->invoke($seeder);

                // Extract template type and language from key
                [$type, $language] = explode('_', $key, 2);
                $subject = $this->getSeederSubject($type, $language);

                $templates[$key] = [
                    'subject' => $subject,
                    'body' => $body,
                ];
            } catch (\ReflectionException $e) {
                // Fallback to legacy method if seeder method doesn't exist
                \Log::warning("Seeder method {$methodName} not found, using fallback");
            }
        }

        return $templates;
    }

    /**
     * Get subject from seeder data - matches the seeder's database inserts
     */
    private function getSeederSubject(string $type, string $language): string
    {
        $subjects = [
            'welcome_en' => 'Welcome to {{app_name}} - Your AI Journey Begins!',
            'welcome_de' => 'Willkommen bei {{app_name}} - Ihre KI-Reise beginnt!',
            'otp_en' => 'Your {{app_name}} Authentication Code',
            'otp_de' => 'Ihr {{app_name}} Authentifizierungscode',
            'invitation_en' => 'You\'re invited to join a {{app_name}} Group Chat',
            'invitation_de' => 'Einladung zu einem {{app_name}} Gruppen-Chat',
            'notification_en' => '{{app_name}} Notification',
            'notification_de' => '{{app_name}} Benachrichtigung',
            'approval_en' => 'Account Created Successfully - Welcome to {{app_name}}!',
            'approval_de' => 'Konto erfolgreich erstellt - Willkommen bei {{app_name}}!',
            'approval_pending_en' => 'Your {{app_name}} Account is Pending Approval',
            'approval_pending_de' => 'Ihr {{app_name}}-Account wurde erfolgreich beantragt',
        ];

        return $subjects[$type.'_'.$language] ?? '{{app_name}} Message';
    }

    /**
     * Get default subject for a template type (legacy fallback)
     */
    private function getDefaultSubject(string $templateType): string
    {
        return match ($templateType) {
            'welcome' => 'Welcome to {{app_name}} - Your AI Journey Begins!',
            'otp' => 'Your {{app_name}} Authentication Code',
            'invitation' => 'You\'re invited to join a {{app_name}} Group Chat',
            'notification' => '{{app_name}} Notification',
            'approval' => 'Account Created Successfully - Welcome to {{app_name}}!',
            'approval_pending' => 'Your {{app_name}} Account is Pending Approval',
            default => '{{app_name}} Message',
        };
    }

    /**
     * Get default body for a template type (legacy fallback)
     */
    private function getDefaultBody(string $templateType): string
    {
        return match ($templateType) {
            'welcome' => '<p>Hello {{user_name}}, welcome to {{app_name}}!</p>',
            'otp' => '<p>Your authentication code: <strong>{{otp}}</strong></p>',
            'invitation' => '<p>You have been invited to {{room_name}}!</p>',
            'notification' => '<p>Hello {{user_name}}, you have a new notification from {{app_name}}.</p>',
            'approval' => '<p>Hello {{user_name}}, your account has been successfully created!</p>',
            'approval_pending' => '<p>Hello {{user_name}}, your account request has been received and is pending approval. You will be notified once your account is activated.</p>',
            default => '<p>Hello {{user_name}}, this is a message from {{app_name}}.</p>',
        };
    }
}
