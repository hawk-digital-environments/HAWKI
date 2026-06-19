<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync\Syncers;


use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\Repositories\SystemPromptRepository;
use App\Services\Ai\Values\SystemPromptType;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\Translation\LocaleService;
use App\Utils\JobMetrics;
use Illuminate\Contracts\Translation\Translator;

/**
 * @internal
 */
readonly class SystemPromptSyncer implements ConfigSyncerInterface
{
    private const array TYPE_TO_TRANSLATION_LABEL_MAP = [
        SystemPromptType::DEFAULT->value => 'Default_Prompt',
        SystemPromptType::SUMMARY->value => 'Summary_Prompt',
        SystemPromptType::PROMPT_IMPROVEMENT->value => 'Improvement_Prompt',
        SystemPromptType::TITLE_GENERATION->value => 'Name_Prompt',
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
            foreach (SystemPromptType::cases() as $type) {
                $translationKey = self::TYPE_TO_TRANSLATION_LABEL_MAP[$type->value] ?? null;
                if ($translationKey) {
                    yield [$type, $locale] => $this->translator->get(
                        $translationKey,
                        locale: (string)$locale
                    );
                }
            }
        }
    }
}
