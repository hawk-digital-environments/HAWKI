<?php
declare(strict_types=1);


namespace App\Services\Translation;


use Illuminate\Contracts\Translation\Translator;

class CustomTranslator
{
    public function __construct(
        private Translator $translator
    )
    {
    }

    public function __invoke($key, array $replace = [], $locale = null)
    {
        return $this->translator->get(...);
    }
}
