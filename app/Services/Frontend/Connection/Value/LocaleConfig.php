<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Services\Translation\Value\Locale;
use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class LocaleConfig implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public array $available;
    
    public function __construct(
        public Locale $preferred,
        public Locale $default,
        array         $available,
    )
    {
        Assert::is($available, static function ($locales) {
            foreach ($locales as $locale) {
                if (!$locale instanceof Locale) {
                    return 'The array contains an element that is not a Locale instance';
                }
            }
            return true;
        }, 'available');
        
        $this->available = array_map(static fn(Locale $l) => $l->toArray(), $available);
    }
}
