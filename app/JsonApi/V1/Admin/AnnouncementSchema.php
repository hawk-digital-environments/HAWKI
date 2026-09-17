<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\AnnouncementRepository;

final class AnnouncementSchema extends Schema
{
    protected const REPOSITORY = AnnouncementRepository::class;
    protected const ATTRIBUTES = [
        'title', 'kind', 'is_published', 'starts_at', 'expires_at', 'seen_count', 'accepted_count', 'is_global', 'is_forced', 'target_roles', 'anchor', 'content',
    ];
    public static string $model = Records\Announcement::class;

    public static function type(): string
    {
        return 'admin-announcements';
    }
}
