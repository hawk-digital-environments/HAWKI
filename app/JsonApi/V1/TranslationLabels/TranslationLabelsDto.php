<?php
declare(strict_types=1);


namespace App\JsonApi\V1\TranslationLabels;


use App\Services\Translation\Value\Locale;

readonly class TranslationLabelsDto
{
    public function __construct(
        public Locale $locale,
        public array  $labels
    )
    {
    }
}
