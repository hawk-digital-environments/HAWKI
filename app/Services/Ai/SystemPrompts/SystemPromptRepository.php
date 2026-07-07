<?php
declare(strict_types=1);


namespace App\Services\Ai\SystemPrompts;


use App\Models\Ai\SystemPrompt;
use App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException;
use App\Services\Ai\SystemPrompts\Values\WellKnownSystemPromptTypes;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use App\Services\System\UsageTypes\UsageContext;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use Illuminate\Database\Eloquent\Builder;

class SystemPromptRepository extends AbstractRepositoryWithContextualScopes
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly UsageContext  $usageContext
    )
    {
    }

    /**
     * Returns the default system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     * If no usage type is provided, the current usage context will be used.
     */
    public function findDefaultPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::DEFAULT, $locale, $usageType);
    }

    /**
     * Returns the summary system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     * If no usage type is provided, the current usage context will be used.
     *
     */
    public function findSummaryPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::SUMMARY, $locale, $usageType);
    }

    /**
     * Returns the improvement system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     * If no usage type is provided, the current usage context will be used.
     */
    public function findImprovementPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::PROMPT_IMPROVEMENT, $locale, $usageType);
    }

    /**
     * Returns the title system prompt for the given locale.
     * If no locale is provided, the default locale will be used.
     * If no usage type is provided, the current usage context will be used.
     */
    public function findTitleGenerationPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::TITLE_GENERATION, $locale, $usageType);
    }

    /**
     * Upserts a system prompt for the given type and locale.
     * If a prompt for the given type and locale already exists, it will be updated with the new content.
     * If no prompt exists, a new one will be created.
     *
     * @param string $promptType The type of the system prompt.
     * @param string $usageType The usage type of the system prompt.
     * @param Locale $locale The locale of the system prompt.
     * @param string $content The content of the system prompt.
     * @return SystemPrompt The upserted system prompt.
     */
    public function upsert(
        string $promptType,
        string $usageType,
        Locale $locale,
        string $content
    ): SystemPrompt
    {
        return $this->getQueryWithoutContextualScopes()
            ->updateOrCreate(
                [
                    'prompt_type' => $promptType,
                    'usage_type' => $usageType,
                    'locale' => $locale
                ],
                [
                    'prompt' => $content
                ]
            );
    }

    /**
     * Finds a system prompt by type, locale, and usage type. If no prompt is found, an exception is thrown.
     *
     * @param string $type The type of the system prompt.
     * @param string|Locale|null $locale The locale of the system prompt. If null, the most likely locale will be used.
     * @param string|null $usageType The usage type of the system prompt. If null, the current usage context will be used.
     * @return SystemPrompt The found system prompt.
     */
    private function findOneOrFail(
        string             $type,
        string|Locale|null $locale = null,
        string|null        $usageType = null
    ): SystemPrompt
    {
        $result = $this->makeBaseQuery($type, $locale, $usageType)->first();

        if (!$result) {
            throw SystemPromptNotFoundException::forTypeAndLocale(
                $type,
                $this->localeService->getMostLikelyLocale($locale),
                $usageType
            );
        }

        return $result;
    }

    /**
     * @return Builder<SystemPrompt>
     */
    private function makeBaseQuery(
        string|null        $type = null,
        string|Locale|null $locale = null,
        string|null        $usageType = null,
        ?ScopeOverrides    $scopeOverrides = null
    ): Builder
    {
        if ($locale) {
            $scopeOverrides = $scopeOverrides ?? $this->makeScopeOverrides(null);
            $scopeOverrides = $scopeOverrides->withForcefullyDisabled('locale');
        }
        if ($usageType) {
            $scopeOverrides = $scopeOverrides ?? $this->makeScopeOverrides(null);
            $scopeOverrides = $scopeOverrides->withForcefullyDisabled('usage_type_overlay');
        }

        $query = $this->getQuery($scopeOverrides);

        if ($type) {
            $query->where('prompt_type', $type);
        }

        if ($locale) {
            $query->where('locale', $this->localeService->getMostLikelyLocale($locale));
        }

        if ($usageType) {
            $query->where('usage_type', $this->usageContext->getForGiven($usageType));
        }

        return $query;
    }

}
