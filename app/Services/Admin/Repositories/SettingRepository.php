<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\SystemSettings;

class SettingRepository extends ResourceRepository
{
    public const RESOURCE = 'settings';

    public function readOne(User $user, string $id): array
    {
        $row = collect(app(SystemSettings::class)->rows())->firstWhere('id', $id);
        abort_if(null === $row, 404);

        return $row;
    }

    public function update(string $id, array $values, User $actor): void
    {
        app(SystemSettings::class)->save($id, $values['value'] ?? null, $actor->id);
    }

    public function reset(string $id): void
    {
        app(SystemSettings::class)->reset($id);
    }

    protected function readContent(User $user, array $filters): array
    {
        return ['rows' => app(SystemSettings::class)->rows(), 'columns' => ['key', 'value', 'default', 'source']];
    }
}
