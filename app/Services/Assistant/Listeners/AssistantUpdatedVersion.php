<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;

class AssistantUpdatedVersion
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly DatabaseManager $db,
        private readonly CarbonClockInterface $clock,
    ) {
    }

    private static ?int $getDebounceUpdateDefault = null;

    private function getDebounceUpdateDefault(): int
    {
        return self::$getDebounceUpdateDefault ??= (int) $this->config->get(
            'assistant.assistant_versions.debounce_seconds',
            10
        );
    }

    public function handle(AssistantUpdatedEvent $event): void
    {
        if ($event->assistant->release_stage === AssistantReleaseStage::DRAFT) {
            return;
        }


        $seconds = $this -> getDebounceUpdateDefault(); 

        $this->db->transaction(function () use ($event, $seconds): void {
            // Lock the latest version row so two concurrent updates cannot
            // both read the same max('version') and both insert duplicate rows
            // (the unique index on (assistant_id, version) is the backstop).
            $latest = $event->assistant
                ->assistantVersions()
                ->latest('version')
                ->lockForUpdate()
                ->first();

            // Time is fully driven by the injected clock: both the "now" read
            // below and the updated_at written on merge/create come from it, so
            // the debounce comparison uses a single source of truth. Eloquent's
            // auto-timestamps are overridden explicitly (updateTimestamps() keeps
            // dirty values), otherwise the comparison would mix clock-time with
            // Eloquent's own Carbon::now() and diverge under test-time control.
            $now = $this->clock->now();

            // Sliding window: if the most recent version was touched within the
            // debounce window, merge this change into it. The update refreshes
            // updated_at, which extends the window for the next change.
            if (null !== $latest && $now->subSeconds($seconds) <= $latest->updated_at) {
                $keys = $this->mergeKeys($latest->changed_keys ?? [], $event->changedKeys);

                $latest->forceFill([
                    'changed_keys' => $keys,
                    'text' => $this->encodeText($keys),
                    'updated_at' => $now,
                ])->save();

                return;
            }

            $lastVersion = $event->assistant->assistantVersions()->max('version') ?? 0.0;

            // version is server-controlled and intentionally not mass-assignable.
            $event->assistant->assistantVersions()->save(
                (new AssistantVersion())->forceFill([
                    'text' => $this->encodeText($event->changedKeys),
                    'version' => $lastVersion + 1.0,
                    'changed_keys' => $event->changedKeys,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        });
    }

    /**
     * @param array<int, string> $existing
     * @param array<int, string> $incoming
     *
     * @return array<int, string>
     */
    private function mergeKeys(array $existing, array $incoming): array
    {
        $merged = array_unique(array_merge(
            array_map('strval', $existing),
            array_map('strval', $incoming),
        ));

        sort($merged);

        return $merged;
    }

    /**
     * @param array<int, string> $keys
     */
    private function encodeText(array $keys): string
    {
        return json_encode(['changes' => $keys], \JSON_THROW_ON_ERROR);
    }
}
