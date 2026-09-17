<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\PermissionService;
use App\Services\Admin\ResourceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

abstract class ResourceRepository
{
    public const RESOURCE = '';
    protected const VERSION_RELATIONS = [];

    final public function authorize(User $user): void
    {
        app(PermissionService::class)->authorize($user, ResourceCatalog::SECTIONS[static::RESOURCE]);
    }

    final public function checkVersion(string $id, ?string $version): void
    {
        abort_unless($version && hash_equals($this->version($this->lockVersionedRow($id)), $version), 412, __('admin.errors.conflict'));
    }

    final public function read(User $user, array $filters): array
    {
        return $this->readContent($user, $this->validateFilters($filters));
    }

    /**
     * Serialize a saved record using the same redaction rules as the collection.
     */
    final public function readOne(User $user, string $id): array
    {
        if (null !== ($row = $this->readOneContent($user, $id))) {
            return $row;
        }

        $definition = $this->definition();
        $table = $definition['table'] ?? (new $definition['model']())->getTable();
        $row = DB::table($table)->where('id', $id)->first();
        abort_if(null === $row, 404);

        return $this->serialize((array) $row, $definition);
    }

    final public function version(array $row): string
    {
        $row = $this->versionableRow($row);

        foreach (static::VERSION_RELATIONS as [$table, $key, $sort]) {
            $query = DB::table($table)->where($key, $row['id'])->orderBy($sort);

            if ('role_user' === $table) {
                $query->orderBy('source');
            }

            $row[$table] = $query->get()->all();
        }

        $row += $this->versionAttributes($row);
        ksort($row);

        return hash('sha256', json_encode($row, \JSON_THROW_ON_ERROR));
    }

    protected function definition(): array
    {
        return [];
    }

    protected function fields(User $user): array
    {
        return $this->definition()['fields'];
    }

    protected function canCreate(User $user): bool
    {
        return $this->definition()['create'] ?? true;
    }

    protected function readContent(User $user, array $filters): array
    {
        $definition = $this->definition();
        $table = $definition['table'] ?? (new $definition['model']())->getTable();
        $query = DB::table($table);
        $columns = $definition['columns'];
        $columnMap = $definition['column_map'] ?? [];
        $searchable = array_values(array_intersect($columns, ['name', 'label', 'model_id', 'provider_id', 'server_label', 'title', 'username', 'email', 'employeetype', 'employee_type', 'slug']));

        if (!empty($filters['search']) && $searchable) {
            $query->where(static function ($q) use ($searchable, $filters, $columnMap): void {
                foreach ($searchable as $field) {
                    $q->orWhere($columnMap[$field] ?? $field, 'like', '%' . $filters['search'] . '%');
                }
            });
        }

        $stored = array_diff($columns, ['api_key_set', 'seen_count', 'accepted_count']);

        foreach (array_filter($filters['where'] ?? [], static fn ($value) => null !== $value && '' !== $value) as $column => $value) {
            abort_unless('id' === $column || \in_array($column, $stored, true), 422);
            $query->where($columnMap[$column] ?? $column, $value);
        }

        $sort = $filters['sort'] ?? 'id';
        abort_unless('id' === $sort || \in_array($sort, $stored, true), 422);
        $total = (clone $query)->count();
        $page = (int) ($filters['page'] ?? 1);
        $size = (int) ($filters['size'] ?? 25);
        $query->orderBy($columnMap[$sort] ?? $sort, $filters['direction'] ?? 'asc');

        if ('id' !== $sort) {
            $query->orderBy('id');
        }

        $rows = $query->offset(($page - 1) * $size)->limit($size)->get()->map(fn ($row) => $this->serialize((array) $row, $definition))->all();
        $fields = $this->fields($user);

        $options = [];

        foreach ($fields as &$field) {
            if (\is_string($field['options'])) {
                $key = $field['options'];

                if (!isset($options[$key])) {
                    $options[$key] = $this->options($key);
                }

                $field['options'] = $options[$key];
            } else {
                $field['options'] = array_map(static fn ($value) => ['value' => $value, 'label' => $value], $field['options']);
            }

            $field['required'] = str_starts_with($field['rules'], 'required');
            unset($field['rules']);
        }

        unset($field);
        $create = $this->canCreate($user);
        // System roles carry a translation key so the client never has to recognize the seeded English name.
        $extra = \in_array(static::RESOURCE, ['users', 'mappings', 'models'], true) ? ['role_catalog' => DB::table('roles')->get(['id', 'display_name', 'name', 'is_system'])->map(static fn ($role) => ['id' => (int) $role->id, 'name' => $role->display_name, 'slug' => $role->name, 'is_system' => (bool) $role->is_system, 'title_label' => $role->is_system ? 'admin.role_labels.' . $role->name : null])->all()] : [];

        return $extra + ['rows' => $rows, 'columns' => $columns, 'fields' => $fields, 'total' => $total, 'page' => $page, 'size' => $size, 'create' => $create, 'delete' => $definition['delete'] ?? true];
    }

    /**
     * Reads a row that does not have a backing table managed by this base repository.
     */
    protected function readOneContent(User $user, string $id): ?array
    {
        return null;
    }

    protected function rowAttributes(array $row): array
    {
        return [];
    }

    protected function versionAttributes(array $row): array
    {
        return [];
    }

    /**
     * Locks and returns the current row used to check a write precondition.
     */
    protected function lockVersionedRow(string $id): array
    {
        $definition = $this->definition();
        $table = $definition['table'] ?? (new $definition['model']())->getTable();
        $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
        abort_unless(null !== $row, 404);

        return (array) $row;
    }

    /**
     * Selects the stable data that participates in a resource version.
     */
    protected function versionableRow(array $row): array
    {
        return $row;
    }

    protected function validateFilters(array $filters): array
    {
        return Validator::make($filters, ['page' => 'sometimes|integer|min:1', 'size' => 'sometimes|integer|min:1|max:100',
            'search' => 'nullable|string|max:255', 'sort' => 'nullable|string|max:100', 'direction' => 'sometimes|in:asc,desc',
            'from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d|after_or_equal:from',
            'group_by' => ['sometimes', Rule::in(['day', 'month', 'model', 'provider', 'type', 'user'])],
            'model' => 'nullable|string|max:255', 'user' => 'nullable|integer',
            'where' => 'sometimes|array', 'where.*' => 'nullable|string|max:255',
        ])->validate();
    }

    private function serialize(array $row, array $definition): array
    {
        $result = ['id' => (string) $row['id'], '_version' => $this->version($row)];

        $fields = array_column($definition['fields'], null, 'key');

        foreach (array_unique(array_merge($definition['columns'], array_keys($fields))) as $key) {
            $column = $definition['column_map'][$key] ?? $key;

            if (!\array_key_exists($column, $row)) {
                continue;
            }

            $type = $fields[$key]['type'] ?? '';

            if (str_starts_with($type, 'secret')) {
                $result[$key . '_set'] = filled($row[$column]);

                continue;
            }

            $value = $row[$column];

            if (\in_array($type, ['json', 'multi', 'markdown-locales'], true)) {
                $value = null === $value ? null : json_decode($value, true);
            }

            if ('boolean' === $type) {
                $value = (bool) $value;
            }

            $result[$key] = $value;
        }

        return $this->rowAttributes($row) + $result;
    }

    private function options(string $key): array
    {
        [$table, $value, $label] = match ($key) {
            'roles' => ['roles', 'id', 'display_name'], 'providers' => ['ai_providers', 'id', 'name'],
            'tools' => ['ai_tools', 'id', 'name'], 'mcp_servers' => ['mcp_servers', 'id', 'server_label'], 'model_keys' => ['ai_models', 'model_id', 'label'],
            default => ['ai_models', 'id', 'label'],
        };

        return DB::table($table)->orderBy($label)->get([$value, $label])->map(static fn ($row) => ['value' => $row->{$value}, 'label' => $row->{$label}])->all();
    }
}
