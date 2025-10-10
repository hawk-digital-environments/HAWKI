<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\Customization\MailTemplateSearchFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class MailTemplateFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            MailTemplateSearchFilter::class,
        ];
    }
}
