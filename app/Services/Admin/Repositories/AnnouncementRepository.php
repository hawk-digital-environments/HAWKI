<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\Repositories\PolicyAnnouncementRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @extends ConfigurationRepository<Announcement>
 */
class AnnouncementRepository extends ConfigurationRepository
{
    public const RESOURCE = 'announcements';

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => Announcement::class, 'columns' => ['title', 'type', 'is_published', 'starts_at', 'expires_at', 'seen_count', 'accepted_count'], 'fields' => [
            $fields->text('title', true), $fields->select('type', ['news', 'system', 'event', 'info', 'policy']),
            $fields->boolean('is_published'), $fields->boolean('is_global'), $fields->boolean('is_forced'),
            $fields->multiple('target_roles', 'roles'),
            $fields->field('starts_at', 'datetime', 'nullable|date'), $fields->field('expires_at', 'datetime', 'nullable|date|after_or_equal:starts_at'),
            $fields->text('anchor'), $fields->field('content', 'markdown-locales', 'required|array'),
        ]];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);
        $rules['content.en_US'] = 'nullable|string|max:200000';
        $rules['content.de_DE'] = 'nullable|string|max:200000';
        $rules['content'] = 'required|array:en_US,de_DE';
        $rules['target_roles.*'] = 'integer|exists:roles,id';

        return $rules;
    }

    protected function prepare(Model $model, array &$data): void
    {
        $this->validateAnnouncement($model, $data);

        if (!$model->exists) {
            $model->setAttribute('view', 'admin');
        }
    }

    protected function deleting(Model $model): void
    {
        if ('policy' === $model->type && $model->is_published) {
            throw ValidationException::withMessages(['type' => __('admin.errors.published_policy')]);
        }
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];
        $result['seen_count'] = DB::table('announcement_user')->where('announcement_id', $row['id'])->whereNotNull('seen_at')->count();
        $result['accepted_count'] = DB::table('announcement_user')->where('announcement_id', $row['id'])->whereNotNull('accepted_at')->count();

        if (!json_decode($row['content'] ?? 'null', true)) {
            $announcement = \App\Models\Announcements\Announcement::findOrFail($row['id']);
            $result['content'] = [];

            foreach (['de_DE', 'en_US'] as $locale) {
                $result['content'][$locale] = app(\App\Services\Announcements\AnnouncementContentResolver::class)->resolve($announcement, $locale)->text ?? '';
            }
        }

        return $result;
    }

    private function validateAnnouncement(Announcement $announcement, array $data): void
    {
        if ($data['is_published'] && !array_filter($data['content'], static fn ($text) => \is_string($text) && '' !== trim($text))) {
            throw ValidationException::withMessages(['content' => __('admin.errors.content_required')]);
        }

        if ($announcement->exists && 'policy' === $announcement->type && $announcement->is_published && ('policy' !== $data['type'] || !$data['is_published'])) {
            throw ValidationException::withMessages(['type' => __('admin.errors.published_policy')]);
        }

        if ('policy' !== $data['type']) {
            return;
        }

        if (!$data['is_global'] || !empty($data['target_roles'])) {
            throw ValidationException::withMessages(['is_global' => __('admin.errors.global_policy')]);
        }

        if (!$data['is_published']) {
            return;
        }

        $overlaps = app(PolicyAnnouncementRepository::class)->findPoliciesOverlapping(
            empty($data['starts_at']) ? null : \Carbon\CarbonImmutable::parse($data['starts_at']),
            empty($data['expires_at']) ? null : \Carbon\CarbonImmutable::parse($data['expires_at']),
            $announcement->id,
        );

        if ($overlaps->isNotEmpty()) {
            throw ValidationException::withMessages(['starts_at' => __('admin.errors.policy_overlap')]);
        }
    }
}
