<?php
declare(strict_types=1);


namespace App\Casts;


use App\Services\System\Container\ServiceLocatorTrait;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsLocale implements CastsAttributes
{
    use ServiceLocatorTrait;

    /**
     * @inheritDoc
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Locale
    {
        return $this->getService(LocaleService::class)->getMostLikelyLocale($value);
    }

    /**
     * @inheritDoc
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof Locale) {
            return $value->lang;
        }
        return $value;
    }
}
