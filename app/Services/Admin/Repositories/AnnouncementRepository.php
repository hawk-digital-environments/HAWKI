<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\AnnouncementPublicationRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends ConfigurationRepository<Announcement>
 */
class AnnouncementRepository extends ConfigurationRepository
{
    public const RESOURCE = 'announcements';

    public function __construct(private readonly AnnouncementPublicationRules $publicationRules)
    {
    }

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
        if (!$model->exists) {
            $model->setAttribute('view', 'admin');
        }

        $this->publicationRules->validate($model, $data);
    }

    protected function deleting(Model $model): void
    {
        $this->publicationRules->validateDeletion($model);
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
}
