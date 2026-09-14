<?php

declare(strict_types=1);

namespace App\Services\Admin;

/**
 * Field metadata shared by admin repositories and their editors.
 */
class ResourceFields
{
    public function text(string $key, bool $required = false): array
    {
        return $this->field($key, 'text', $required ? 'required|string|max:255' : 'nullable|string|max:255');
    }

    public function json(string $key): array
    {
        return $this->field($key, 'json', 'nullable|array');
    }

    public function boolean(string $key): array
    {
        return $this->field($key, 'boolean', 'required|boolean', default: false);
    }

    public function select(string $key, array $values): array
    {
        return $this->field($key, 'select', 'required|in:' . implode(',', $values), $values);
    }

    public function reference(string $key, string $table, string $options): array
    {
        return $this->field($key, 'select', "required|integer|exists:{$table},id", options: $options);
    }

    public function multiple(string $key, array|string $options): array
    {
        return $this->field($key, 'multi', 'present|array', options: $options, default: []);
    }

    public function secrets(): array
    {
        return [
            $this->field('api_key', 'secret', 'nullable|string|max:16000'),
            $this->field('additional_config', 'secret-json', 'nullable|array'),
        ];
    }

    public function field(string $key, string $type, string $rules, array|string $options = [], mixed $default = null): array
    {
        $result = compact('key', 'type', 'rules', 'options');

        if (null !== $default) {
            $result['default'] = $default;
        }

        return $result;
    }
}
