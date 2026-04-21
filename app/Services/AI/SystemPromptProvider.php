<?php
declare(strict_types=1);


namespace App\Services\AI;


use App\Services\AI\Value\SystemPrompt;
use App\Services\AI\Value\SystemPromptType;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Translation\Translator;

/**
 * Helps you to retrieve system prompts in different languages.
 */
#[Singleton]
class SystemPromptProvider
{
    private array $resolvedPrompts = [];
    
    public function __construct(
        private readonly Translator    $translator,
        private readonly LocaleService $locales
    )
    {
    }
    
    /**
     * Resolves a system prompt for the given type and locale.
     * If no locale is provided, the default locale will be used.
     * @param SystemPromptType $type A system prompt type
     * @param string|Locale|null $locale The desired locale, or null for the default locale
     * @return SystemPrompt
     */
    public function getPrompt(SystemPromptType $type, string|Locale|null $locale = null): SystemPrompt
    {
        $realLocale = $this->locales->getMostLikelyLocale($locale);
        $cacheKey = $type->value . '|' . $realLocale->lang;
        if (isset($this->resolvedPrompts[$cacheKey])) {
            return $this->resolvedPrompts[$cacheKey];
        }
        
        $text = $this->translator->get(
            $type->value,
            locale: (string)$realLocale
        );
        
        $prompt = new SystemPrompt(
            type: $type,
            locale: $realLocale,
            text: is_string($text) ? $text : ''
        );
        
        $this->resolvedPrompts[$cacheKey] = $prompt;
        
        return $prompt;
    }
    
    /**
     * Returns the default system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     * @param string|Locale|null $locale
     * @return SystemPrompt
     */
    public function getDefaultPrompt(string|Locale|null $locale = null): SystemPrompt
    {
        return $this->getPrompt(SystemPromptType::DEFAULT, $locale);
    }
    
    /**
     * Returns the summary system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     *
     * @param string|Locale|null $locale The desired locale, or null to use the default locale.
     * @return SystemPrompt The summary system prompt.
     */
    public function getSummaryPrompt(string|Locale|null $locale = null): SystemPrompt
    {
        return $this->getPrompt(SystemPromptType::SUMMARY, $locale);
    }
    
    /**
     * Returns the improvement system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     *
     * @param string|Locale|null $locale The desired locale, or null to use the default locale.
     * @return SystemPrompt The improvement system prompt.
     */
    public function getImprovementPrompt(string|Locale|null $locale = null): SystemPrompt
    {
        return $this->getPrompt(SystemPromptType::IMPROVEMENT, $locale);
    }
    
    /**
     * Returns the name system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     *
     * @param string|Locale|null $locale The desired locale, or null to use the default locale.
     * @return SystemPrompt The name system prompt.
     */
    public function getNamePrompt(string|Locale|null $locale = null): SystemPrompt
    {
        return $this->getPrompt(SystemPromptType::NAME, $locale);
    }
    
    /**
     * Returns all available system prompts for the given locale.
     * If no locale is provided, the default locale will be used.
     *
     * @param string|Locale|null $locale The desired locale, or null to use the default locale.
     * @return array An array of all system prompts.
     */
    public function getAllPrompts(string|Locale|null $locale = null): array
    {
        $prompts = [];
        foreach (SystemPromptType::cases() as $type) {
            $prompts[] = $this->getPrompt($type, $locale);
        }
        return $prompts;
    }
}
