<?php
declare(strict_types=1);


namespace App\Services\Translation;


use Illuminate\Contracts\Translation\Translator;

trait TranslatingTrait
{
    private Translator|null $translator = null;

    public function setTranslator(Translator $translator): void
    {
        $this->translator = $translator;
    }

    protected function __($key, array $replace = [], $locale = null)
    {
        return ($this->translator ?? app(Translator::class))
            ->get($key, $replace, $locale);
    }
}
