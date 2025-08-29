<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\ProviderSearchFilter;
use App\Orchid\Filters\ProviderStatusFilter;
use App\Orchid\Filters\ProviderApiFormatFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class ProviderSettingsFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            ProviderSearchFilter::class,
            ProviderApiFormatFilter::class,
            ProviderStatusFilter::class,
        ];
    }
}
