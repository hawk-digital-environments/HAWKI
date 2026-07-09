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

/**
 * Database access layer for the {@see SystemPrompt} model.
 *
 * System prompts are locale- and usage-type-scoped text templates that are injected as
 * the AI system message for specific operations (default chat, summarisation, prompt
 * improvement, title generation).  Each prompt is uniquely identified by the triple
 * `(prompt_type, usage_type, locale)`.
 *
 * The repository resolves the most appropriate locale via {@see LocaleService} and honours
 * the active usage context via {@see UsageContext}, so callers rarely need to pass these
 * explicitly.  When a locale or usage type is provided explicitly, the corresponding
 * contextual scope is disabled for that single query so the explicit value takes precedence
 * over any ambient scope.
 *
 * Named convenience methods ({@see findDefaultPrompt()}, {@see findSummaryPrompt()}, etc.)
 * cover the well-known prompt types; they all throw {@see SystemPromptNotFoundException}
 * when no matching record exists so callers receive a meaningful error rather than a null.
 */
class SystemPromptRepository extends AbstractRepositoryWithContextualScopes
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly UsageContext  $usageContext
    )
    {
    }

    /**
     * Returns the default system prompt for the given locale and usage type.
     *
     * Falls back to the ambient locale (via {@see LocaleService}) and the current usage context
     * (via {@see UsageContext}) when either argument is omitted.
     *
     * @throws \App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException
     */
    public function findDefaultPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::DEFAULT, $locale, $usageType);
    }

    /**
     * Returns the summary system prompt for the given locale and usage type.
     *
     * Falls back to the ambient locale and current usage context when either argument is omitted.
     *
     * @throws \App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException
     */
    public function findSummaryPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::SUMMARY, $locale, $usageType);
    }

    /**
     * Returns the prompt-improvement system prompt for the given locale and usage type.
     *
     * Falls back to the ambient locale and current usage context when either argument is omitted.
     *
     * @throws \App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException
     */
    public function findImprovementPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::PROMPT_IMPROVEMENT, $locale, $usageType);
    }

    /**
     * Returns the title-generation system prompt for the given locale and usage type.
     *
     * Falls back to the ambient locale and current usage context when either argument is omitted.
     *
     * @throws \App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException
     */
    public function findTitleGenerationPrompt(string|Locale|null $locale = null, string|null $usageType = null): SystemPrompt
    {
        return $this->findOneOrFail(WellKnownSystemPromptTypes::TITLE_GENERATION, $locale, $usageType);
    }

    /**
     * Creates or updates a system prompt identified by `(prompt_type, usage_type, locale)`.
     *
     * Used by the config-file sync flow to seed or refresh prompts from a YAML definition.
     * Contextual scopes are bypassed so the upsert is not restricted by ambient locale or
     * usage-type filters.
     *
     * @param string $promptType One of the {@see WellKnownSystemPromptTypes} constants.
     * @param string $usageType  The usage type this prompt applies to.
     * @param Locale $locale     The locale this prompt is written in.
     * @param string $content    The raw prompt text.
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
     * Looks up a system prompt by type, locale, and usage type and throws when not found.
     *
     * The locale argument is resolved to the most likely concrete locale via
     * {@see LocaleService::getMostLikelyLocale()} before querying, so passing a generic
     * language tag (e.g. `"de"`) will be expanded to the best matching DB locale.
     *
     * @throws \App\Services\Ai\SystemPrompts\Exceptions\SystemPromptNotFoundException
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
     * Builds a filtered query for the SystemPrompt model.
     *
     * When an explicit locale or usage type is provided, the corresponding contextual scope is
     * disabled so that the explicit WHERE clause takes full control rather than being shadowed
     * by the ambient scope value.
     *
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
