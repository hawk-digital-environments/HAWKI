<?php

namespace App\Listeners;

use App\Events\AssistantUpdated;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class AssistantUpdatedVersion
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function handle(AssistantUpdated $event): void
    {
        if ($event->assistant->release_stage === ReleaseStage::DRAFT->value) {
            return;
        }

        /** @var int $seconds */
        $seconds = (int) $this->config->get('assistant.versions.debounce_seconds', 10);

        $latest = $event->assistant->versions()->latest('version')->first();

        // Sliding window: if the most recent version was touched within the
        // debounce window, merge this change into it. The update refreshes
        // updated_at, which extends the window for the next change.
        if ($latest !== null && $latest->updated_at >= now()->subSeconds($seconds)) {
            $keys = $this->mergeKeys($latest->changed_keys ?? [], $event->changedKeys);

            $latest->update([
                'changed_keys' => $keys,
                'text' => $this->encodeText($keys),
            ]);

            return;
        }

        $lastVersion = $event->assistant->versions()->max('version') ?? 0.0;

        $event->assistant->versions()->create([
            'text' => $this->encodeText($event->changedKeys),
            'version' => $lastVersion + 1.0,
            'changed_keys' => $event->changedKeys,
        ]);
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $incoming
     * @return array<int, string>
     */
    private function mergeKeys(array $existing, array $incoming): array
    {
        $merged = array_unique(array_merge(
            array_map('strval', $existing),
            array_map('strval', $incoming),
        ));

        sort($merged);

        return array_values($merged);
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function encodeText(array $keys): string
    {
        return json_encode(['changes' => array_values($keys)], JSON_THROW_ON_ERROR);
    }
}
