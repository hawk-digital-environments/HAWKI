<?php

declare(strict_types=1);

namespace App\Services\Announcements;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\Repositories\PolicyAnnouncementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

readonly class AnnouncementPublicationRules
{
    public function __construct(
        private PolicyAnnouncementRepository $policies,
        private AnnouncementContentResolver $contentResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(Announcement $announcement, array $data, bool $validatePolicyWindow = true): void
    {
        $candidate = clone $announcement;
        $candidate->fill($data);

        $inlineContent = \is_array($candidate->content) ? $candidate->content : [];
        $hasInlineContent = array_filter(
            $inlineContent,
            static fn ($text): bool => \is_string($text) && trim($text) !== '',
        ) !== [];

        if ($candidate->is_published && !$hasInlineContent && $this->contentResolver->resolve($candidate) === null) {
            throw ValidationException::withMessages(['content' => __('admin.errors.content_required')]);
        }

        if (null !== $candidate->starts_at
            && null !== $candidate->expires_at
            && $candidate->expires_at->lt($candidate->starts_at)) {
            throw ValidationException::withMessages(['expires_at' => __('admin.validation.dates')]);
        }

        if ($announcement->exists
            && 'policy' === $announcement->type
            && $announcement->is_published
            && ('policy' !== $candidate->type || !$candidate->is_published)) {
            throw ValidationException::withMessages(['type' => __('admin.errors.published_policy')]);
        }

        if ('policy' !== $candidate->type) {
            return;
        }

        if (!$candidate->is_global || !empty($candidate->target_roles)) {
            throw ValidationException::withMessages(['is_global' => __('admin.errors.global_policy')]);
        }

        if (!$candidate->is_published || !$validatePolicyWindow) {
            return;
        }

        $overlaps = $this->policies->findPoliciesOverlapping(
            empty($candidate->starts_at) ? null : CarbonImmutable::parse($candidate->starts_at),
            empty($candidate->expires_at) ? null : CarbonImmutable::parse($candidate->expires_at),
            $announcement->exists ? (int) $announcement->id : null,
        );

        if ($overlaps->isNotEmpty()) {
            throw ValidationException::withMessages(['starts_at' => __('admin.errors.policy_overlap')]);
        }
    }

    public function validateDeletion(Announcement $announcement): void
    {
        if ('policy' === $announcement->type && $announcement->is_published) {
            throw ValidationException::withMessages(['type' => __('admin.errors.published_policy')]);
        }
    }
}
