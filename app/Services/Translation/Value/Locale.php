<?php
declare(strict_types=1);


namespace App\Services\Translation\Value;


readonly class Locale implements \JsonSerializable, \Stringable
{
    public function __construct(
        /**
         * The locale code, e.g. 'en_US', 'fr_FR', 'de_DE'
         */
        public string $lang,
        /**
         * The HTML language code, e.g. 'en-US', 'fr-FR', 'de-DE'
         */
        public string $htmlLang,
        /**
         * The name of the language in that language, e.g. 'English', 'Français', 'Deutsch'
         */
        public string $nameInLanguage,
        /**
         * A short name for the language, e.g. 'EN', 'FR', 'DE'
         */
        public string $shortName,
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return $this->lang;
    }
    
    public function __toString(): string
    {
        return $this->lang;
    }
    
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
            'htmlLang' => $this->htmlLang,
            'nameInLanguage' => $this->nameInLanguage,
            'shortName' => $this->shortName,
        ];
    }
    
    /**
     * Convert the locale back to an array compatible with the legacy array format.
     * @return array
     */
    public function toLegacyArray(): array
    {
        return [
            'id' => $this->lang,
            'name' => $this->nameInLanguage,
            'label' => $this->shortName,
        ];
    }
    
    /**
     * @param array<array{active: bool, id: string, name: string, label: string}> $config
     * @return Locale[]
     */
    public static function createListByConfig(array $config): array
    {
        $result = [];
        foreach ($config as $item) {
            if (!$item['active']) {
                continue;
            }
            
            $htmlLang = str_replace('_', '-', $item['id']);
            if (str_starts_with($htmlLang, 'zh-')) {
                // Special case for Chinese: use 'zh-Hans' or 'zh-Hant'
                if (str_ends_with($htmlLang, '-CN') || str_ends_with($htmlLang, '-SG')) {
                    $htmlLang = 'zh-Hans';
                } elseif (str_ends_with($htmlLang, '-TW') || str_ends_with($htmlLang, '-HK') || str_ends_with($htmlLang, '-MO')) {
                    $htmlLang = 'zh-Hant';
                }
            }
            
            $result[$item['id']] = new self(
                lang: $item['id'],
                htmlLang: $htmlLang,
                nameInLanguage: $item['name'],
                shortName: $item['label'],
            );
        }
        
        return $result;
    }
}
