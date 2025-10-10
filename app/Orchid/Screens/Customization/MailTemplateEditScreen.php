<?php

namespace App\Orchid\Screens\Customization;

use App\Models\MailTemplate;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\MailTemplateEditLayout;
use App\Orchid\Layouts\Customization\MailTemplateEnglishLayout;
use App\Orchid\Layouts\Customization\MailTemplateGermanLayout;
use App\Orchid\Layouts\Customization\MailTemplatePlaceholderHelpLayout;
use App\Orchid\Traits\MailTemplateManagementTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MailTemplateEditScreen extends Screen
{
    use MailTemplateManagementTrait;
    use OrchidLoggingTrait;

    /**
     * @var MailTemplate
     */
    public $mailTemplate;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query($template_type = null): iterable
    {
        // If $template_type is a string, find the mail templates
        if (is_string($template_type)) {
            $templateType = urldecode($template_type);

            // Get German and English templates
            $deTemplate = MailTemplate::where('type', $templateType)
                ->where('language', 'de')
                ->first();
            $enTemplate = MailTemplate::where('type', $templateType)
                ->where('language', 'en')
                ->first();

            return [
                'mailTemplate' => [
                    'type' => $templateType,
                    'description' => $deTemplate ? $deTemplate->description : ($enTemplate ? $enTemplate->description : ''),
                    'de_subject' => $deTemplate ? $deTemplate->subject : '',
                    'de_body' => $deTemplate ? $deTemplate->body : '',
                    'en_subject' => $enTemplate ? $enTemplate->subject : '',
                    'en_body' => $enTemplate ? $enTemplate->body : '',
                ],
                'isEdit' => true,
            ];
        }

        // New mail template
        return [
            'mailTemplate' => [
                'type' => '',
                'description' => '',
                'de_subject' => '',
                'de_body' => '',
                'en_subject' => '',
                'en_body' => '',
            ],
            'isEdit' => false,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        $isEdit = request()->route('template_type') ? true : false;

        return $isEdit
            ? 'Edit Mail Template: '.urldecode(request()->route('template_type'))
            : 'Create Mail Template';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        $isEdit = request()->route('template_type') ? true : false;

        return $isEdit
            ? 'Edit mail template content for multiple languages'
            : 'Create new mail template content for multiple languages';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [

            Button::make('Reset')
                ->icon('bs.arrow-counterclockwise')
                ->method('reset')
                ->confirm('Are you sure you want to reset this template to default? This will overwrite the current content.'),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),

        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,

            Layout::block(MailTemplateEditLayout::class)
                ->title('Mail Template Information')
                ->description('Template type and description for this mail template.'),

            Layout::block(MailTemplateGermanLayout::class)
                ->title('German Content')
                ->description('German email subject and content for this template.'),

            Layout::block(MailTemplateEnglishLayout::class)
                ->title('English Content')
                ->description('English email subject and content for this template.'),

            Layout::block(MailTemplatePlaceholderHelpLayout::class)
                ->title('Available Placeholders')
                ->description('Use these placeholders in your email templates. They will be automatically replaced with actual values.'),
        ];
    }

    /**
     * Save the mail template.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $data = $request->get('mailTemplate');

        // Get template_type from form data or route parameter (for edit mode)
        $templateType = $data['type'] ?? urldecode(request()->route('template_type'));

        // Adjust validation rules for edit mode
        $isEdit = request()->route('template_type') !== null;
        $validationRules = [
            'mailTemplate.description' => 'nullable|string|max:500',
            'mailTemplate.de_subject' => 'nullable|string',
            'mailTemplate.de_body' => 'nullable|string',
            'mailTemplate.en_subject' => 'nullable|string',
            'mailTemplate.en_body' => 'nullable|string',
        ];

        if (! $isEdit) {
            // Only validate type for create mode
            $validationRules['mailTemplate.type'] = 'required|string|max:255';
        }

        $request->validate($validationRules);

        try {
            // In edit mode, preserve existing description
            $isEdit = request()->route('template_type') !== null;

            if ($isEdit) {
                // Get existing description from database
                $existingTemplate = MailTemplate::where('type', $templateType)->first();
                $description = $existingTemplate ? $existingTemplate->description : '';
            } else {
                // New template - use provided description
                $description = $data['description'] ?? '';
            }

            // Save German template
            if (! empty($data['de_subject']) || ! empty($data['de_body'])) {
                MailTemplate::updateOrCreate(
                    ['type' => $templateType, 'language' => 'de'],
                    [
                        'description' => $description,
                        'subject' => $data['de_subject'],
                        'body' => $data['de_body'],
                    ]
                );
            }

            // Save English template
            if (! empty($data['en_subject']) || ! empty($data['en_body'])) {
                MailTemplate::updateOrCreate(
                    ['type' => $templateType, 'language' => 'en'],
                    [
                        'description' => $description,
                        'subject' => $data['en_subject'],
                        'body' => $data['en_body'],
                    ]
                );
            }

            $this->logModelOperation('update', 'mail_template', $templateType, 'success', [
                'description' => $description,
            ]);

            Toast::info('Mail template has been saved successfully.');

            // Stay on the same page instead of redirecting to the list
            return back();

        } catch (\Exception $e) {
            Log::error('Error saving mail template: '.$e->getMessage());
            Toast::error('Error saving mail template: '.$e->getMessage());

            return back()->withInput();
        }
    }

    /**
     * Reset the mail template to default content using the unified trait method
     */
    public function reset(Request $request)
    {
        return $this->resetMailTemplate($request);
    }
}
