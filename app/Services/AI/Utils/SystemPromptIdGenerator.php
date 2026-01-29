<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Services\AI\Value\SystemPrompt;
use App\Services\AI\Value\SystemPromptType;
use App\Services\Translation\LocaleService;

readonly class SystemPromptIdGenerator
{
    private array $sortedLangs;
    private int $sortedLangsCount;
    
    public function __construct(
        LocaleService $localeService
    )
    {
        $langs = array_values(array_map('strval', $localeService->getAvailableLocales()));
        sort($langs);
        $this->sortedLangs = $langs;
        $this->sortedLangsCount = count($langs);
    }
    
    /**
     * Generate a stable numeric ID for a given SystemPrompt.
     * The ID is based on the locale and type of the prompt.
     * This ensures that each unique combination of locale and type has a unique ID.
     * @param SystemPrompt $prompt
     * @return int
     */
    public function getIdFor(SystemPrompt $prompt): int
    {
        $langIndex = array_search($prompt->locale->lang, $this->sortedLangs, true);
        $typeIndex = array_search($prompt->type, SystemPromptType::cases(), true);
        
        if ($langIndex === false || $typeIndex === false) {
            throw new \InvalidArgumentException('Invalid locale or type');
        }
        
        return (($typeIndex + 1) * $this->sortedLangsCount) + ($langIndex + 1);
    }
}
