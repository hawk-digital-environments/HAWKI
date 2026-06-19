<?php
declare(strict_types=1);


namespace App\Services\Translation\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Translation\Exception\DefaultLocaleIsNotActiveException;
use App\Services\Translation\Value\Locale;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class LocaleConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * The default locale of the application.
     */
    public readonly Locale $default;

    /**
     * The list of available locales in the application.
     * Each locale is represented by its language code, e.g. "en", "fr", "es".
     * The default locale must be included in this list.
     * @var Locale[]
     */
    public readonly array $available;

    public static function make(Repository $repo): static
    {
        $defaultLocaleConfig = $repo->get('locale.default_language');
        $localeConfig = $repo->get('locale.langs');
        $availableLocales = Locale::createListByConfig($localeConfig);
        $activeLocaleIds = array_map(static fn(Locale $locale) => $locale->lang, $availableLocales);

        if (!in_array($defaultLocaleConfig, $activeLocaleIds, true)) {
            throw new DefaultLocaleIsNotActiveException(
                defaultLocale: $defaultLocaleConfig,
                activeLocaleIds: $activeLocaleIds,
            );
        }

        $defaultLocale = null;
        foreach ($availableLocales as $locale) {
            if ($locale->lang === $defaultLocaleConfig) {
                $defaultLocale = $locale;
                break;
            }
        }

        return self::fromArray([
            'default' => $defaultLocale,
            'available' => $availableLocales,
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'locale';
    }

    /**
     * @inheritDoc
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'default' => $this->default->lang,
            'available' => array_values(array_map(static fn(Locale $locale) => $locale->toArray(), $this->available)),
        ];
    }
}
