<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Mail\TemplateMail;
use App\Models\MailTemplate;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\MailTemplateFiltersLayout;
use App\Orchid\Layouts\Customization\MailTemplateListLayout;
use App\Orchid\Traits\MailTemplateManagementTrait;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\MailPlaceholderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class MailTemplatesScreen extends Screen
{
    use MailTemplateManagementTrait;
    use OrchidLoggingTrait;

    /**
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Get unique mail templates (one record per type, preferring German if available)
        $mailTemplates = MailTemplate::query()
            ->select('*')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MIN(id)')
                    ->from('mail_templates')
                    ->groupBy('type');
            })
            ->filters()
            ->defaultSort('type')
            ->paginate(50);

        return [
            'mailtemplates' => $mailTemplates,
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
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Reset All')
                ->icon('bs.arrow-clockwise')
                ->confirm('Are you sure you want to reset all mail templates to default values?')
                ->method('resetAllTemplates'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,
            MailTemplateFiltersLayout::class,
            MailTemplateListLayout::class,
        ];
    }

    /**
     * Send a test email using the specified template
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTestMail(Request $request)
    {
        try {
            $templateType = $request->get('template_type');
            $user = Auth::user();

            if (! $user || ! $user->email) {
                Toast::error('No email address found for your account.');

                return back();
            }

            // Get the template (prefer German, fallback to English)
            $template = MailTemplate::where('type', $templateType)
                ->where('language', 'de')
                ->first();

            if (! $template) {
                $template = MailTemplate::where('type', $templateType)
                    ->where('language', 'en')
                    ->first();
            }

            if (! $template) {
                Toast::error("Template '{$templateType}' not found.");

                return back();
            }

            // Get template-specific test data
            $testData = MailPlaceholderService::getTestData($templateType, $user);

            // Create and send the email using TemplateMail
            $mail = TemplateMail::fromTemplate($template, $testData, $user);

            // Add [TEST] prefix to subject
            $mail->subject = '[TEST] '.$mail->subject;

            Mail::to($user->email)->send($mail);

            $this->logModelOperation('test_mail', 'mail_template', $templateType, 'success', [
                'template_type' => $templateType,
                'recipient' => $user->email,
                'language' => $template->language,
            ]);

            Toast::success("Test email sent to {$user->email} using template '{$templateType}' ({$template->language}).");

        } catch (\Exception $e) {
            Log::error('Error sending test mail: '.$e->getMessage());
            Toast::error('Error sending test mail: '.$e->getMessage());
        }

        return back();
    }
}
