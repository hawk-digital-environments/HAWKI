<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\Customization\AnnouncementAudienceFilter;
use App\Orchid\Filters\Customization\AnnouncementForcedFilter;
use App\Orchid\Filters\Customization\AnnouncementIdentifierFilter;
use App\Orchid\Filters\Customization\AnnouncementTypeFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class AnnouncementFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            AnnouncementIdentifierFilter::class,
            AnnouncementTypeFilter::class,
            AnnouncementForcedFilter::class,
            AnnouncementAudienceFilter::class,
        ];
    }
}
