<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\SystemSettings;

class SettingRepository extends ResourceRepository
{
    public const RESOURCE = 'settings';

    public function update(string $id, array $values, User $actor): void
    {
        app(SystemSettings::class)->save($id, $values['value'] ?? null, $actor->id);
    }

    public function reset(string $id): void
    {
        app(SystemSettings::class)->reset($id);
    }

    protected function readOneContent(User $user, string $id): ?array
    {
        return $this->withVersion(app(SystemSettings::class)->row($id));
    }

    protected function readContent(User $user, array $filters): array
    {
        return [
            'rows' => array_map($this->withVersion(...), app(SystemSettings::class)->rows()),
            'columns' => ['key', 'value', 'default', 'source'],
        ];
    }

    protected function lockVersionedRow(string $id): array
    {
        return app(SystemSettings::class)->row($id, true);
    }

    protected function versionableRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'value' => $row['value'],
            'default' => $row['default'],
            'source' => $row['source'],
        ];
    }

    private function withVersion(array $row): array
    {
        return ['_version' => $this->version($row)] + $row;
    }
}
