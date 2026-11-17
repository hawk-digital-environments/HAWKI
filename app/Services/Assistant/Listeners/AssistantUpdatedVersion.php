<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use Illuminate\Database\DatabaseManager;

/**
 * Records content edits on the assistant's working-draft row (version 0.0).
 *
 * The changed keys accumulate there until the next approval promotes the
 * draft, which resets them — they describe the delta between the published
 * state and the pending draft, which is exactly what a reviewer diff shows.
 * Edits never create additional version rows, so the debounce window of the
 * previous journal-style implementation is obsolete; the row is still locked
 * so concurrent edits serialise.
 */
class AssistantUpdatedVersion
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function handle(AssistantUpdatedEvent $event): void
    {
        if ([] === $event->changedKeys) {
            return;
        }

        $this->db->transaction(function () use ($event): void {
            $draft = $event->assistant->assistantVersions()
                ->where('version', AssistantVersion::DRAFT_VERSION)
                ->lockForUpdate()
                ->first();

            if (null === $draft) {
                // Covers assistants whose rows predate the draft model.
                $event->assistant->assistantVersions()->save((new AssistantVersion())->forceFill([
                    'text' => '',
                    'version' => AssistantVersion::DRAFT_VERSION,
                    'changed_keys' => $this->mergeKeys([], $event->changedKeys),
                ]), );

                return;
            }

            $draft->forceFill([
                'changed_keys' => $this->mergeKeys($draft->changed_keys ?? [], $event->changedKeys),
            ])->save();
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
}
