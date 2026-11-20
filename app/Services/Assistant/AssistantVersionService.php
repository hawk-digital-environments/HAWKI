<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\Exceptions\AssistantVersionNotFoundException;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;

/**
 * Versioned state for the assistant review workflow.
 *
 * Every assistant carries a mutable working-draft row at version 0.0 (the
 * "next version" placeholder). Content edits accumulate their changed keys on
 * it. When the creator submits for review, the current state is frozen onto
 * the draft row (`payload`), so the review always judges the exact submitted
 * state and every submit overwrites the previous one — including changes to
 * an already publicly released assistant.
 *
 * Approving a review promotes the draft row to the next published version
 * number (max + 1), respawns a fresh draft row seeded with the published
 * payload, and restores that payload onto the live assistant — what the
 * review judged is exactly what goes live. Denying or requesting revision
 * leaves the draft untouched.
 *
 * Rollback comes in two flavours: for a publicly released assistant (or one
 * with a review pending) it is itself a submission — the target state is
 * frozen onto the draft and a pending review (re)opened, applying as a new
 * version only on approval; for an unpublished, unreviewed assistant it
 * applies immediately and destructively, deleting the versions above the
 * target.
 *
 * The payload references child rows (settings, tags, tools, prompts,
 * attachments, avatar) by id instead of duplicating them, so restoring does
 * not trickle down into the other models. Knowledge attachments are the one
 * exception: they are not restored (only recorded in the payload), because
 * removing rows would cascade into stored-file deletion and break restoring
 * older versions again.
 */
#[Singleton()]
readonly class AssistantVersionService
{
    public function __construct(
        private DatabaseManager $db,
        private AssistantRepository $assistantRepository,
        private AssistantReviewRepository $reviewRepository,
    ) {
    }

    /**
     * The assistant's mutable draft row (version 0.0), created on first access.
     */
    public function draftVersion(Assistant $assistant): AssistantVersion
    {
        $existing = $assistant->assistantVersions()
            ->where('version', AssistantVersion::DRAFT_VERSION)
            ->first();

        if (null !== $existing) {
            return $existing;
        }

        $draft = (new AssistantVersion())->forceFill([
            'text' => '',
            'version' => AssistantVersion::DRAFT_VERSION,
            'payload' => $this->capture($assistant),
        ]);
        $assistant->assistantVersions()->save($draft);

        return $draft;
    }

    /**
     * Freeze a state onto the draft row (overwrite semantics); the note is
     * kept when the submit carries none. A payload override freezes the
     * target version's state instead of the live one and replaces the
     * changed keys with the diff between the two.
     *
     * @param null|array<string, mixed> $frozenPayload
     */
    public function freeze(Assistant $assistant, ?string $note = null, ?array $frozenPayload = null): AssistantVersion
    {
        return $this->db->transaction(function () use ($assistant, $note, $frozenPayload): AssistantVersion {
            // Lock the draft row like the update listener does, so a
            // concurrent edit cannot interleave with the freeze.
            $draft = $assistant->assistantVersions()
                ->where('version', AssistantVersion::DRAFT_VERSION)
                ->lockForUpdate()
                ->first();

            $payload = $frozenPayload ?? $this->capture($assistant);
            $changedKeys = null !== $frozenPayload
                ? $this->payloadDiff($this->capture($assistant), $frozenPayload)
                : null;

            if (null === $draft) {
                $draft = (new AssistantVersion())->forceFill([
                    'version' => AssistantVersion::DRAFT_VERSION,
                    'payload' => $payload,
                    'text' => $note ?? '',
                    'changed_keys' => $changedKeys,
                ]);
                $assistant->assistantVersions()->save($draft);

                return $draft;
            }

            $draft->forceFill([
                'payload' => $payload,
                ...(null !== $note ? ['text' => $note] : []),
                ...(null !== $changedKeys ? ['changed_keys' => $changedKeys] : []),
            ])->save();

            return $draft;
        });
    }

    /**
     * Promote the draft row to the next published version number and respawn
     * a fresh draft seeded with the just-published payload. Called when a
     * review is approved.
     */
    public function promoteDraft(Assistant $assistant): AssistantVersion
    {
        return $this->db->transaction(function () use ($assistant): AssistantVersion {
            $draft = $assistant->assistantVersions()
                ->where('version', AssistantVersion::DRAFT_VERSION)
                ->lockForUpdate()
                ->first();

            if (null === $draft) {
                // Legacy assistants without a draft row: promote a snapshot
                // of the live state so no approval is lost.
                $draft = (new AssistantVersion())->forceFill([
                    'text' => '',
                    'version' => AssistantVersion::DRAFT_VERSION,
                    'payload' => $this->capture($assistant),
                ]);
                $assistant->assistantVersions()->save($draft);
            }

            $next = (float) $assistant->assistantVersions()
                ->where('version', '>', AssistantVersion::DRAFT_VERSION)
                ->max('version') + 1.0;

            $draft->forceFill(['version' => $next])->save();

            // The new draft starts identical to what was just published; the
            // changed keys reset because the pending changes are now live.
            $fresh = (new AssistantVersion())->forceFill([
                'text' => '',
                'version' => AssistantVersion::DRAFT_VERSION,
                'payload' => $draft->payload,
                'changed_keys' => [],
            ]);
            $assistant->assistantVersions()->save($fresh);

            return $draft;
        });
    }

    /**
     * Restore an earlier published version: as a submission while the
     * assistant is public or under review, immediately and destructively
     * otherwise. The release stage is untouched either way — rollback is a
     * content operation, not a publication one.
     */
    public function rollback(Assistant $assistant, float $target): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $target): Assistant {
            $locked = $this->assistantRepository->lockForUpdate($assistant);

            if (null === $locked) {
                return $assistant;
            }

            $assistant->setRawAttributes($locked->getAttributes());

            $version = $assistant->assistantVersions()
                ->where('version', $target)
                ->lockForUpdate()
                ->first();

            if (null === $version || AssistantVersion::DRAFT_VERSION === $target) {
                throw AssistantVersionNotFoundException::forVersion($target);
            }

            if (null === $version->payload) {
                throw AssistantVersionNotFoundException::withoutPayload($target);
            }

            $review = $assistant->assistantReview()->lockForUpdate()->first();
            $pendingReview = null !== $review && AssistantReviewStatus::PENDING === $review->status;

            if ($assistant->release_stage->isPublic() || $pendingReview) {
                // The rollback is itself a submission: freeze the target state and (re)open the review.
                $this->freeze($assistant, "Rollback to version {$target}", $version->payload);
                $this->reviewRepository->updateOrCreateForAssistant($assistant->id, [
                    'status' => AssistantReviewStatus::PENDING->value,
                    'reason' => null,
                    'reviewer_id' => null,
                    'reviewed_at' => null,
                ]);
                $assistant->requested_release_stage = $assistant->release_stage;
                $assistant->save();

                return $assistant;
            }

            $assistant->assistantVersions()
                ->where('version', '>', $target)
                ->delete();

            $this->restore($assistant, $version->payload);

            // The draft restarts from the restored state.
            $assistant->assistantVersions()
                ->where('version', AssistantVersion::DRAFT_VERSION)
                ->delete();
            $assistant->assistantVersions()->save((new AssistantVersion())->forceFill([
                'text' => '',
                'version' => AssistantVersion::DRAFT_VERSION,
                'payload' => $version->payload,
                'changed_keys' => [],
            ]), );

            // Unpublished and nothing pending: earlier decision records stay untouched.
            $assistant->requested_release_stage = null;
            $assistant->save();

            return $assistant;
        });
    }

    /**
     * Serialize the assistant's reviewable state: scalar attributes plus
     * references to the child rows that shape it.
     *
     * @return array<string, mixed>
     */
    public function capture(Assistant $assistant): array
    {
        $assistant->load([
            'settingValues',
            'assistantUserPrompts',
            'assistantTags',
            'ai_tools',
            'assistantAttachments',
            'assistantAvatar',
        ]);

        return [
            'attributes' => $assistant->only([
                'name',
                'handle',
                'system_prompt',
                'greeting',
                'description',
                'detail_description',
                'allow_remix',
                'allow_model_select',
                'category_id',
                'model',
                'max_tokens',
                'temp',
                'top_p',
            ]),
            'setting_values' => $assistant->settingValues
                ->map(static fn ($value): array => [
                    'setting_id' => $value->setting_id,
                    'value' => $value->value,
                ])
                ->all(),
            'user_prompts' => $assistant->assistantUserPrompts
                ->map(static fn ($prompt): string => (string) $prompt->text)
                ->all(),
            'tag_ids' => $assistant->assistantTags->pluck('id')->all(),
            'tool_ids' => $assistant->ai_tools->pluck('id')->all(),
            // Informational: recorded so a reviewer can see what the version
            // contained, never restored (see class docblock).
            'attachment_ids' => $assistant->assistantAttachments->pluck('id')->all(),
            'avatar_id' => $assistant->assistantAvatar?->id,
        ];
    }

    /**
     * Apply a captured payload back onto the live assistant.
     *
     * @param array<string, mixed> $payload
     */
    public function restore(Assistant $assistant, array $payload): void
    {
        $attributes = \is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];
        $assistant->forceFill($attributes)->save();

        $settingValues = \is_array($payload['setting_values'] ?? null) ? $payload['setting_values'] : [];
        $assistant->settingValues()->delete();
        if (\count($settingValues) > 0) {
            $assistant->settingValues()->createMany($settingValues);
        }

        $userPrompts = \is_array($payload['user_prompts'] ?? null) ? $payload['user_prompts'] : [];
        $assistant->assistantUserPrompts()->delete();
        if (\count($userPrompts) > 0) {
            $assistant->assistantUserPrompts()->createMany(array_map(static fn (string $text): array => ['text' => $text], $userPrompts));
        }

        $tagIds = \is_array($payload['tag_ids'] ?? null) ? $payload['tag_ids'] : [];
        $assistant->assistantTags()->sync($tagIds);

        $toolIds = \is_array($payload['tool_ids'] ?? null) ? $payload['tool_ids'] : [];
        $assistant->ai_tools()->sync($toolIds);
    }

    /**
     * The keys in which two captured payloads differ — the changed-keys list
     * a reviewer sees for a rollback submission.
     *
     * @param array<string, mixed> $live
     * @param array<string, mixed> $target
     *
     * @return list<string>
     */
    private function payloadDiff(array $live, array $target): array
    {
        $keys = [];

        foreach ($target['attributes'] ?? [] as $key => $value) {
            if (($live['attributes'][$key] ?? null) !== $value) {
                $keys[] = $key;
            }
        }

        foreach (['setting_values', 'user_prompts', 'tag_ids', 'tool_ids'] as $relation) {
            if (($live[$relation] ?? null) !== ($target[$relation] ?? null)) {
                $keys[] = $relation;
            }
        }

        \sort($keys);

        return $keys;
    }
}
