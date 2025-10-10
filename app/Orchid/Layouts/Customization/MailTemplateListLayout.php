<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\MailTemplate;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class MailTemplateListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'mailtemplates';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('type', 'Template Type')
                ->sort()
                ->render(function (MailTemplate $mailTemplate) {
                    return Link::make($mailTemplate->type)
                        ->route('platform.customization.mailtemplates.edit', urlencode($mailTemplate->type));
                }),

            TD::make('description', 'Description')
                ->render(function (MailTemplate $mailTemplate) {
                    $description = $mailTemplate->description ?? '';
                    if (empty($description)) {
                        return '<span class="text-secondary fst-italic">No description</span>';
                    }
                    $preview = strlen($description) > 100 ? substr($description, 0, 100).'...' : $description;

                    return "<span class=\"text-dark\">{$preview}</span>";
                }),

            TD::make('languages', 'Languages')
                ->render(function (MailTemplate $mailTemplate) {
                    // Get all available languages for this template type
                    $availableLanguages = MailTemplate::where('type', $mailTemplate->type)
                        ->pluck('language')
                        ->map(function ($lang) {
                            return match ($lang) {
                                'de_DE', 'de' => ['code' => 'DE', 'class' => 'bg-primary'],
                                'en_US', 'en' => ['code' => 'EN', 'class' => 'bg-success'],
                                default => ['code' => strtoupper(substr($lang, 0, 2)), 'class' => 'bg-secondary'],
                            };
                        })
                        ->sortBy('code');

                    $badges = $availableLanguages->map(function ($lang) {
                        return "<span class=\"badge rounded-pill {$lang['class']} me-1\">{$lang['code']}</span>";
                    })->implode('');

                    return $badges ?: '<span class="text-muted">No languages</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('120px'),

            TD::make('updated_at', 'Last Updated')
                ->render(function (MailTemplate $mailTemplate) {
                    return $mailTemplate->updated_at?->format('Y-m-d H:i') ?? '';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (MailTemplate $mailTemplate) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.mailtemplates.edit', urlencode($mailTemplate->type))
                            ->icon('bs.pencil'),
                        Button::make('Test Mail')
                            ->icon('bs.envelope')
                            ->confirm('Send a test email with this template to your account?')
                            ->method('sendTestMail', ['template_type' => $mailTemplate->type]),
                        Button::make('Reset')
                            ->icon('bs.arrow-clockwise')
                            ->confirm('Are you sure you want to reset this mail template to default?')
                            ->method('resetMailTemplate', ['template_type' => $mailTemplate->type]),

                    ])),
        ];
    }
}
