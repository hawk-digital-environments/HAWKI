<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSetting;
use App\Services\MailTemplateService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create default mail template settings if they don't exist
        // DO NOT use MailTemplateService here as it creates circular dependency!
        
        $templates = [
            'welcome' => [
                'subject' => 'Willkommen bei HAWKI2!',
                'body' => '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Willkommen bei HAWKI2</title></head><body><h1>🎉 Willkommen bei HAWKI2!</h1><p>Hallo {{user_name}}!</p><p>Willkommen bei HAWKI2 - dem intelligenten Chat-System.</p></body></html>'
            ],
            'otp' => [
                'subject' => 'HAWKI2 Log-In Code: {{otp}}',
                'body' => '<!DOCTYPE html><html><head><meta charset="utf-8"><title>HAWKI2 Log-In Code</title></head><body><h1>HAWKI2 Log-in Code</h1><p>Hallo <strong>{{user_name}}</strong>,</p><p>Ihr Log-in Code lautet: <strong>{{otp}}</strong></p><p>Gültig für 5 Minuten</p></body></html>'
            ],
            'invitation' => [
                'subject' => 'Einladung zu {{room_name}} bei HAWKI2',
                'body' => '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Einladung zu {{room_name}}</title></head><body><h1>📩 Einladung zu {{room_name}}</h1><p>Hallo {{user_name}}!</p><p>{{inviter_name}} hat Sie zu dem Raum <strong>{{room_name}}</strong> bei HAWKI2 eingeladen.</p><a href="{{invitation_url}}">Einladung annehmen</a></body></html>'
            ]
        ];
        
        foreach ($templates as $templateId => $content) {
            // Only create if not exists to avoid overwriting user customizations
            AppSetting::firstOrCreate(
                ['key' => "mail_templates.{$templateId}.subject"],
                [
                    'value' => $content['subject'],
                    'group' => 'mail_templates',
                    'type' => 'string',
                    'is_public' => false,
                ]
            );
            
            AppSetting::firstOrCreate(
                ['key' => "mail_templates.{$templateId}.body"],
                [
                    'value' => $content['body'],
                    'group' => 'mail_templates',
                    'type' => 'text',
                    'is_public' => false,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove mail template settings
        AppSetting::where('group', 'mail_templates')->delete();
    }
};
