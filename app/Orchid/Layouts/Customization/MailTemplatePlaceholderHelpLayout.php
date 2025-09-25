<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Services\MailPlaceholderService;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class MailTemplatePlaceholderHelpLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return array
     */
    public function fields(): iterable
    {
        $testData = MailPlaceholderService::getTestData('welcome');

        $fields = [];

        // Standard placeholders
        $standardPlaceholders = [
            '{{app_name}}', '{{user_name}}', '{{user_email}}',
            '{{app_url}}', '{{current_date}}', '{{current_datetime}}',
        ];

        foreach ($standardPlaceholders as $placeholder) {
            $realValue = $testData[$placeholder] ?? 'N/A';

            $fields[] = Group::make([
                Label::make('label_'.str_replace(['{', '}'], '', $placeholder))
                    ->title('Placeholder')
                    ->value($placeholder)
                    ->class('fw-bold'),
                Label::make('value_'.str_replace(['{', '}'], '', $placeholder))
                    ->title('Real Value')
                    ->value($realValue)
                    ->class('badge bg-secondary fs-5'),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr');
        }

        // Template-specific placeholders
        $templatePlaceholders = [
            '{{otp_code}}', '{{invitation_link}}', '{{room_name}}',
            '{{inviter_name}}', '{{login_url}}', '{{dashboard_url}}',
        ];

        foreach ($templatePlaceholders as $placeholder) {
            $realValue = $testData[$placeholder] ?? 'Template-specific';

            $fields[] = Group::make([
                Label::make('label_'.str_replace(['{', '}'], '', $placeholder))
                    ->title('Placeholder')
                    ->value($placeholder)
                    ->class('fw-bold'),
                Label::make('value_'.str_replace(['{', '}'], '', $placeholder))
                    ->title('Real Value')
                    ->value($realValue)
                    ->class('badge bg-secondary fs-5'),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr');
        }

        return $fields;
    }
}
