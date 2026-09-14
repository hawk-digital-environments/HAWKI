<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Casts\Contracts\CastableInstanceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @template TModel of Model
 */
abstract class ConfigurationRepository extends ResourceRepository
{
    protected const RELATIONS = [];

    final public function save(?int $id, array $values, \App\Models\User $actor): int
    {
        $definition = $this->definition();
        abort_if(!$id && false === ($definition['create'] ?? true), 405);
        $class = $definition['model'];
        $model = $id ? $class::withoutGlobalScopes()->lockForUpdate()->findOrFail($id) : new $class();

        foreach ($definition['fields'] as $field) {
            $key = $field['key'];

            if ($id && ($field['immutable'] ?? false) && isset($values[$key]) && $model->getRawOriginal($key) !== $values[$key]) {
                throw ValidationException::withMessages([$key => __('admin.errors.immutable')]);
            }
        }

        $data = Validator::make($values, $this->rules($id, $values))->validate();
        $original = $model->getRawOriginal();
        $this->prepare($model, $data);

        foreach ($data as $key => $value) {
            if (\in_array($key, static::RELATIONS, true)) {
                continue;
            }

            if (\in_array($key, ['api_key', 'additional_config'], true) && ('' === $value || null === $value)) {
                continue;
            }

            try {
                // HAWKI's structured AI fields require value objects, not raw arrays.
                $current = $model->getAttribute($key);
                $model->setAttribute($key, $current instanceof CastableInstanceInterface ? $current::fromArray($value ?? []) : $value);
            } catch (\InvalidArgumentException|\TypeError $exception) {
                throw ValidationException::withMessages([$key => __('admin.errors.invalid_value')]);
            }
        }

        $model->save();
        $this->saved($model, $data, $original);

        return (int) $model->getKey();
    }

    final public function delete(int $id, \App\Models\User $actor): void
    {
        $definition = $this->definition();
        abort_if(false === ($definition['delete'] ?? true), 405);
        $model = $definition['model']::withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
        $this->deleting($model);
        $model->delete();
    }

    protected function rules(?int $id, array $values): array
    {
        return array_column($this->definition()['fields'], 'rules', 'key');
    }

    /**
     * @param TModel $model
     */
    protected function prepare(Model $model, array &$data): void
    {
        $model->setAttribute('admin_managed', true);
    }

    /**
     * @param TModel $model
     */
    protected function saved(Model $model, array $data, array $original): void
    {
    }

    /**
     * @param TModel $model
     */
    protected function deleting(Model $model): void
    {
    }
}
