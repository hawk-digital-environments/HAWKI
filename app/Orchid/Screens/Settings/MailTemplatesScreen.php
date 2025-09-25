<?php

namespace App\Orchid\Screens\Settings;

use App\Models\MailTemplate;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\MailTemplateService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MailTemplatesScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Construct the screen
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Get mail templates from new mail_templates table
        $mailTemplates = MailTemplate::all();

        return [
            'mailTemplates' => $mailTemplates,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Mail Templates';
    }

    public function description(): ?string
    {
        return 'Manage and customize email templates for system notifications.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return $this->buildMailTemplatesLayout();
    }

    /**
     * Build layout for mail templates editing
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildMailTemplatesLayout()
    {
        $templateService = app(MailTemplateService::class);
        $mailTemplates = MailTemplate::all();

        $layouts = [];

        foreach ($mailTemplates as $template) {
            $layouts[] = Layout::rows([
                Group::make([
                    Label::make('template_label_'.$template->type)
                        ->title($template->description ?: ucfirst($template->type).' Template')
                        ->help('Template-ID: '.$template->type.' | Sprache: '.$template->language),
                ])->fullWidth(),

                Input::make('mail_template_subject_'.$template->id)
                    ->title('Betreffzeile')
                    ->value($template->subject)
                    ->horizontal(),

                Code::make('mail_template_body_'.$template->id)
                    ->title('E-Mail Content (HTML)')
                    ->value($template->body)
                    ->language('html')
                    ->lineNumbers()
                    ->rows(25)
                    ->theme('github')
                    ->help($templateService->getPlaceholderHelpText($template->type, 'body'))
                    ->horizontal(),

                Group::make([
                    Button::make('Template speichern')
                        ->method('saveMailTemplate')
                        ->parameters(['template_id' => $template->id])
                        ->type(Color::SUCCESS)
                        ->icon('check'),

                    Button::make('Template zurücksetzen')
                        ->method('resetMailTemplate')
                        ->parameters(['template_id' => $template->id])
                        ->type(Color::WARNING)
                        ->confirm('Möchten Sie dieses Template auf die Standardwerte zurücksetzen?')
                        ->icon('refresh'),
                ])->autoWidth(),

            ])->title($template->description ?: ucfirst($template->type).' Template');
        }

        if (empty($layouts)) {
            $layouts[] = Layout::rows([
                Label::make('no_templates')
                    ->title('Keine Mail-Templates gefunden')
                    ->help('Führen Sie `php artisan db:seed --class=MailTemplateSeeder` aus, um Standard-Templates zu erstellen.'),
            ]);
        }

        return $layouts;
    }

    /**
     * Save a mail template
     *
     * @return void
     */
    public function saveMailTemplate(Request $request)
    {
        try {
            $templateId = $request->get('template_id');
            $template = MailTemplate::findOrFail($templateId);

            $subjectField = 'mail_template_subject_'.$templateId;
            $bodyField = 'mail_template_body_'.$templateId;

            $subject = $request->get($subjectField);
            $body = $request->get($bodyField);

            if (! $subject || ! $body) {
                Toast::error('Subject und Body sind erforderlich.');

                return;
            }

            $template->update([
                'subject' => $subject,
                'body' => $body,
            ]);

            Toast::success("Template '{$template->description}' wurde erfolgreich gespeichert.");
            Log::info('Mail template updated', [
                'template_id' => $templateId,
                'template_type' => $template->type,
            ]);

        } catch (\Exception $e) {
            Toast::error('Fehler beim Speichern des Templates: '.$e->getMessage());
            Log::error('Failed to save mail template', [
                'error' => $e->getMessage(),
                'template_id' => $request->get('template_id'),
            ]);
        }
    }

    /**
     * Reset a mail template to default values
     *
     * @return void
     */
    public function resetMailTemplate(Request $request)
    {
        try {
            $templateId = $request->get('template_id');
            $template = MailTemplate::findOrFail($templateId);
            $templateService = app(MailTemplateService::class);

            // Get default content based on template type
            $defaultContent = $templateService->getDefaultTemplateContent($template->type);

            if (! $defaultContent || ! isset($defaultContent['subject']) || ! isset($defaultContent['body'])) {
                Toast::error('Keine Standardwerte für diesen Template-Typ verfügbar.');

                return;
            }

            $template->update([
                'subject' => $defaultContent['subject'],
                'body' => $defaultContent['body'],
            ]);

            Toast::success("Template '{$template->description}' wurde auf Standardwerte zurückgesetzt.");
            Log::info('Mail template reset to defaults', [
                'template_id' => $templateId,
                'template_type' => $template->type,
            ]);

        } catch (\Exception $e) {
            Toast::error('Fehler beim Zurücksetzen des Templates: '.$e->getMessage());
            Log::error('Failed to reset mail template', [
                'error' => $e->getMessage(),
                'template_id' => $request->get('template_id'),
            ]);
        }
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
