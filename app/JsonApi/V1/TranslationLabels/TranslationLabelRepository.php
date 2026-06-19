<?php

namespace App\JsonApi\V1\TranslationLabels;

use App\Services\Translation\LocaleService;
use Illuminate\Translation\Translator;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class TranslationLabelRepository extends AbstractRepository
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly Translator    $translator
    )
    {

    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        $locale = $this->localeService->getLocale($resourceId);
        if (!$locale) {
            return null;
        }

        $labels = $this->translator->get('*', locale: $locale->lang);
        return new TranslationLabelsDto(
            locale: $locale,
            labels: $labels
        );
    }
}
