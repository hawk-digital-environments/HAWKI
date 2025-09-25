<?php

namespace App\Orchid\Traits;

use App\Services\MailTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Support\Facades\Toast;

trait MailTemplateManagementTrait
{
    use OrchidLoggingTrait;

    /**
     * Reset a single mail template using the unified service method
     */
    public function resetMailTemplate(Request $request)
    {
        $templateType = $request->get('template_type') ?? request()->route('template_type');

        if (! $templateType) {
            Toast::error('Template type not found.');

            return back();
        }

        $templateType = urldecode($templateType);

        try {
            $mailTemplateService = app(MailTemplateService::class);
            $result = $mailTemplateService->resetTemplates($templateType);

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    Toast::error($error);
                }

                return back();
            }

            $this->logModelOperation('reset', 'mail_template', $templateType, 'success', [
                'reset_count' => $result['reset_count'],
                'reset_to_default' => true,
            ]);

            Toast::success("Mail template '{$templateType}' has been reset to default values ({$result['reset_count']} templates updated).");

            return back();

        } catch (\Exception $e) {
            Log::error('Error resetting mail template: '.$e->getMessage());
            Toast::error('Error resetting mail template: '.$e->getMessage());

            return back();
        }
    }

    /**
     * Reset all mail templates using the unified service method
     */
    public function resetAllTemplates()
    {
        try {
            $mailTemplateService = app(MailTemplateService::class);
            $result = $mailTemplateService->resetTemplates(); // null = all templates

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    Toast::error($error);
                }

                return back();
            }

            $this->logModelOperation('reset_all', 'mail_template', 'all', 'success', [
                'reset_count' => $result['reset_count'],
                'template_types' => $result['template_types'],
                'reset_to_default' => true,
            ]);

            Toast::success("All mail templates have been reset to default values ({$result['reset_count']} templates updated).");

            return back();

        } catch (\Exception $e) {
            Log::error('Error resetting all mail templates: '.$e->getMessage());
            Toast::error('Error resetting all mail templates: '.$e->getMessage());

            return back();
        }
    }
}
