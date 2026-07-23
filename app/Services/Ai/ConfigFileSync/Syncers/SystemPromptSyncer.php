<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync\Syncers;


use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\SystemPrompts\SystemPromptRepository;
use App\Services\Ai\SystemPrompts\Values\WellKnownSystemPromptTypes;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\Translation\LocaleService;
use App\Utils\JobMetrics;
use Illuminate\Contracts\Translation\Translator;

/**
 * Syncs system prompts from translation files into the database.
 *
 * For every locale returned by {@see LocaleService::getAvailableLocales()} and every
 * well-known prompt type in {@see TYPE_TO_TRANSLATION_LABEL_MAP}, the syncer fetches
 * the prompt text from the translation system and upserts it via
 * {@see SystemPromptRepository::upsert()}. This ensures the database always reflects the
 * current state of the translation files for the main-app usage type.
 *
 * The hash produced by {@see getCurrentHash()} covers all locale×type combinations, so
 * adding a new locale or modifying any translation file will trigger a re-sync.
 *
 * @internal
 */
readonly class SystemPromptSyncer implements ConfigSyncerInterface
{
    private const array TYPE_TO_TRANSLATION_LABEL_MAP = [
        WellKnownSystemPromptTypes::DEFAULT => 'Default_Prompt',
        WellKnownSystemPromptTypes::SUMMARY => 'Summary_Prompt',
        WellKnownSystemPromptTypes::PROMPT_IMPROVEMENT => 'Improvement_Prompt',
        WellKnownSystemPromptTypes::TITLE_GENERATION => 'Name_Prompt',
    ];

    public function __construct(
        private LocaleService          $localeService,
        private Translator             $translator,
        private SystemPromptRepository $systemPromptRepository
    )
    {
    }

    public function getCurrentHash(): string
    {
        return md5(
            json_encode(iterator_to_array($this->getAvailablePrompts(), false))
        );
    }

    public function sync(JobMetrics $metrics): void
    {
        foreach ($this->getAvailablePrompts() as $k => $content) {
            [$type, $locale] = $k;
            $this->systemPromptRepository->upsert(
                promptType: $type,
                usageType: WellKnownUsageTypes::MAIN_APP,
                locale: $locale,
                content: $content
            );
            $metrics->increment('System prompt');
        }
    }

    private function getAvailablePrompts(): iterable
    {
        foreach ($this->localeService->getAvailableLocales() as $locale) {
            foreach (self::TYPE_TO_TRANSLATION_LABEL_MAP as $type => $translationKey) {
                yield [$type, $locale] => $this->translator->get(
                    $translationKey,
                    locale: (string)$locale
                );
            }
        }
    }
}
