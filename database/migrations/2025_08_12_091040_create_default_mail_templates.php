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
        $templateService = app(MailTemplateService::class);
        
        $templates = [
            'welcome' => $templateService->getTemplateContent('welcome'),
            'otp' => $templateService->getTemplateContent('otp'),
            'invitation' => $templateService->getTemplateContent('invitation'),
        ];
        
        foreach ($templates as $templateId => $content) {
            // Create subject setting
            AppSetting::updateOrCreate(
                ['key' => "mail_templates.{$templateId}.subject"],
                [
                    'value' => $content['subject'],
                    'group' => 'mail_templates',
                    'type' => 'string',
                    'is_public' => false,
                ]
            );
            
            // Create body setting
            AppSetting::updateOrCreate(
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
