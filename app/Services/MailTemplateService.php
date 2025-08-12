<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing email templates from database
 */
class MailTemplateService
{
    /**
     * Get mail template content from database with placeholder replacement
     *
     * @param string $templateId (welcome, otp, invitation)
     * @param array $data Replacement data for placeholders
     * @return array ['subject' => string, 'body' => string]
     */
    public function getTemplateContent(string $templateId, array $data = []): array
    {
        // Load template from database
        $subjectKey = "mail_templates.{$templateId}.subject";
        $bodyKey = "mail_templates.{$templateId}.body";
        
        $subject = $this->getSettingValue($subjectKey, $this->getDefaultSubject($templateId));
        $body = $this->getSettingValue($bodyKey, $this->getDefaultBody($templateId));
        
        // Replace placeholders
        $subject = $this->replacePlaceholders($subject, $data);
        $body = $this->replacePlaceholders($body, $data);
        
        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Replace placeholders in template content
     *
     * @param string $content
     * @param array $data
     * @return string
     */
    private function replacePlaceholders(string $content, array $data): string
    {
        // Standard app placeholders
        $placeholders = [
            '{{app_name}}' => config('app.name', 'HAWKI'),
            '{{app_url}}' => config('app.url'),
            '{{current_date}}' => now()->format('d.m.Y'),
            '{{current_datetime}}' => now()->format('d.m.Y H:i:s'),
        ];

        // Add user-specific placeholders
        if (isset($data['user'])) {
            $user = $data['user'];
            $placeholders['{{user_name}}'] = $user->username ?? $user->name ?? 'Benutzer';
            $placeholders['{{user_email}}'] = $user->email ?? '';
        }

        // Add template-specific placeholders from data
        foreach ($data as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $placeholders["{{{$key}}}"] = $value;
            }
        }

        // Replace all placeholders
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Get available placeholders for a specific template
     *
     * @param string $templateId
     * @return array ['placeholder' => 'description']
     */
    public function getAvailablePlaceholders(string $templateId): array
    {
        $commonPlaceholders = [
            '{{app_name}}' => 'Name of the application',
            '{{user_name}}' => 'Name of the user',
            '{{user_email}}' => 'User\'s email address',
            '{{app_url}}' => 'URL of the application',
            '{{current_date}}' => 'Current date',
            '{{current_datetime}}' => 'Current date and time'
        ];

        $specificPlaceholders = [];

        switch ($templateId) {
            case 'otp':
                $specificPlaceholders = [
                    '{{otp}}' => 'One-Time Password (OTP)'
                ];
                break;
            case 'invitation':
                $specificPlaceholders = [
                    '{{room_name}}' => 'Name of the room',
                    '{{inviter_name}}' => 'Name of the inviter',
                    '{{invitation_url}}' => 'Invitation URL'
                ];
                break;
        }

        return array_merge($commonPlaceholders, $specificPlaceholders);
    }

    /**
     * Get formatted placeholder help text for UI
     *
     * @param string $templateId
     * @param string $fieldType 'subject' or 'body'
     * @return string
     */
    public function getPlaceholderHelpText(string $templateId, string $fieldType = 'body'): string
    {
        $placeholders = $this->getAvailablePlaceholders($templateId);
        
        $helpText = ($fieldType === 'body' ? 'HTML template with syntax highlighting. ' : '') . 'Available placeholders:<br>';
        foreach ($placeholders as $placeholder => $description) {
            $helpText .= "<code>{$placeholder}</code> - {$description}<br>";
        }
        
        return rtrim($helpText, '<br>');
    }

    /**
     * Get setting value from database
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    private function getSettingValue(string $key, string $default = ''): string
    {
        try {
            $setting = AppSetting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        } catch (\Exception $e) {
            Log::warning("Failed to load mail template setting: {$key}", ['error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Get default subject for template
     *
     * @param string $templateId
     * @return string
     */
    public function getDefaultSubject(string $templateId): string
    {
        return match ($templateId) {
            'welcome' => 'Willkommen bei {{app_name}}!',
            'otp' => '{{app_name}} Log-In Code: {{otp}}',
            'invitation' => 'Einladung zu {{room_name}} bei {{app_name}}',
            default => '{{app_name}} Nachricht',
        };
    }

    /**
     * Get default body for template
     *
     * @param string $templateId
     * @return string
     */
    public function getDefaultBody(string $templateId): string
    {
        return match ($templateId) {
            'welcome' => $this->getDefaultWelcomeBody(),
            'otp' => $this->getDefaultOtpBody(),
            'invitation' => $this->getDefaultInvitationBody(),
            default => '<p>Hallo {{user_name}},</p><p>Dies ist eine Nachricht von {{app_name}}.</p>',
        };
    }

    /**
     * Default welcome email body
     */
    private function getDefaultWelcomeBody(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Willkommen bei {{app_name}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Willkommen bei {{app_name}}!</h1>
    </div>
    
    <div class="content">
        <h2>Hallo {{user_name}}!</h2>
        
        <p>Willkommen bei {{app_name}} - dem intelligenten Chat-System.</p>
        
        <h3>{{app_name}} Features:</h3>
        <ul>
            <li>KI-gestützte Konversationen</li>
            <li>Sichere Benutzerauthentifizierung</li>
            <li>Gruppenchats und Collaboration</li>
            <li>Real-time Messaging mit WebSockets</li>
            <li>Passkey-basierte Sicherheit</li>
        </ul>
        
        <a href="{{app_url}}" class="button">
            Zu {{app_name}}
        </a>
        
        <p>Ihre E-Mail-Adresse: {{user_email}}</p>
    </div>
    
    <div class="footer">
        <p>{{app_name}} - Powered by Laravel</p>
        <p>Gesendet am: {{current_datetime}}</p>
    </div>
</body>
</html>';
    }

    /**
     * Default OTP email body
     */
    private function getDefaultOtpBody(): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{app_name}} Log-In Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .otp-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-label {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            font-family: "Courier New", monospace;
            margin: 10px 0;
        }
        .validity {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 10px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #0c5460;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{app_name}} Log-in Code</h1>
        </div>
        
        <p>Hallo <strong>{{user_name}}</strong>,</p>
        
        <p>Sie haben einen Log-in Code für Ihren {{app_name}} Account angefordert.</p>
        
        <div class="otp-container">
            <div class="otp-label">Der Log-in Code lautet:</div>
            <div class="otp-code">{{otp}}</div>
            <div class="validity">Gültig für 5 Minuten</div>
        </div>
        
        <div class="info">
            <strong>Hinweis:</strong> Geben Sie diesen Code in der Anwendung ein, um fortzufahren.
        </div>
        
        <div class="warning">
            <strong>Sicherheitshinweis:</strong> Falls Sie diese E-Mail nicht angefordert haben, ignorieren Sie sie bitte. Teilen Sie diesen Code niemals mit anderen.
        </div>
        
        <div class="footer">
            <p>Mit freundlichen Grüßen,<br>
            Ihr <strong>{{app_name}}</strong> Team</p>
            
            <div>Gesendet am: {{current_datetime}}</div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Default invitation email body
     */
    private function getDefaultInvitationBody(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einladung zu {{room_name}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📩 Einladung zu {{room_name}}</h1>
    </div>
    
    <div class="content">
        <h2>Hallo {{user_name}}!</h2>
        
        <p>{{inviter_name}} hat Sie zu dem Raum <strong>{{room_name}}</strong> bei {{app_name}} eingeladen.</p>
        
        <p>In diesem Raum können Sie:</p>
        <ul>
            <li>Mit anderen Teilnehmern chatten</li>
            <li>KI-gestützte Gespräche führen</li>
            <li>Dateien und Ideen teilen</li>
            <li>In Echtzeit zusammenarbeiten</li>
        </ul>
        
        <a href="{{invitation_url}}" class="button">
            Einladung annehmen
        </a>
        
        <p><strong>Ihre E-Mail:</strong> {{user_email}}</p>
    </div>
    
    <div class="footer">
        <p>{{app_name}} - Zusammenarbeit neu gedacht</p>
        <p>Gesendet am: {{current_datetime}}</p>
    </div>
</body>
</html>';
    }
}
