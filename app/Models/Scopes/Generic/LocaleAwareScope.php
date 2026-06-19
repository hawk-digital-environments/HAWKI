<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use App\Models\Scopes\Traits\LocaleAwareScopeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LocaleAwareScope implements Scope
{
    use LocaleAwareScopeTrait;

    public function __construct(
        private readonly string            $fieldName = 'locale',
        // If given, we should fall back to the default locale and only show those of the current locale that are available.
        private readonly array|string|null $discriminatorFieldsForOverlay = null
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $currentLocale = $this->getCurrentLocale();
        $defaultLocale = $this->getDefaultLocale();

        $treatAsOverlay = !empty($this->discriminatorFieldsForOverlay) && $currentLocale->lang !== $defaultLocale->lang;

        if ($treatAsOverlay) {
            $this->applyOverlay($builder, $currentLocale->lang, $defaultLocale->lang);
        } else {
            $this->applyFilter($builder, $currentLocale->lang);
        }
    }

    private function applyFilter(
        Builder $builder,
        string  $localeValue
    ): void
    {
        $builder->where($this->fieldName, $localeValue);
    }

    private function applyOverlay(
        Builder $builder,
        string  $currentLocaleValue,
        string  $defaultLocaleValue
    ): void
    {
        $builder->where(function (Builder $query) use ($currentLocaleValue, $defaultLocaleValue) {
            $query->where($this->fieldName, $currentLocaleValue)
                ->orWhere(function (Builder $query) use ($defaultLocaleValue) {
                    $query->where($this->fieldName, $defaultLocaleValue);

                    foreach ((array)$this->discriminatorFieldsForOverlay as $field) {
                        $query->whereNull($field);
                    }
                });
        });
    }
}
