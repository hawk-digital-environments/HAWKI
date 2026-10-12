<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings;

use App\Services\System\Http\Attributes\ValidateInput;
use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

/**
 * Generic request validation for the `user-settings` resource — no bespoke request
 * class per settings class.
 *
 * The `rules()` are built dynamically from the settings classes of the addressed
 * namespace (the route's resource id for fetch/update; the client-generated resource
 * id for create) and their `#[ValidateInput]` attributes, prefixed with each class's
 * public key so the rule keys match the nested request body:
 * `'core.theme' => 'sometimes|in:light,dark'`.
 *
 * The framework validator runs the rules and converts failures to JSON:API error
 * objects with source pointers; only rule-bearing keys reach the write path, so
 * un-annotated properties are never fillable.
 */
class UserSettingRequest extends ResourceRequest
{
    public function __construct(
        private readonly UserSettingsRegistry $registry,
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null,
    ) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    /**
     * Get the validation rules for the resource.
     */
    public function rules(): array
    {
        $route = $this->jsonApi()->route();

        if ($route->hasResourceId()) {
            // Fetch/update: the namespace is the route's resource id. An unknown
            // namespace needs no rule here — the repository's find() returns null and
            // the request 404s.
            $namespace = $route->resourceId();

            return $this->rulesForNamespace($namespace);
        }

        // Create (store): the client-generated resource id selects the namespace and
        // must be a registered one.
        return [
            'id' => 'required|in:' . implode(',', $this->registry->namespaces()),
            ...$this->rulesForNamespace($this->validationData()['id'] ?? null),
        ];
    }

    /**
     * Builds the nested rule map for all settings classes of the namespace:
     * `{publicKey}.{property}` → rule.
     *
     * @return array<string, string>
     */
    private function rulesForNamespace(?string $namespace): array
    {
        if (null === $namespace) {
            return [];
        }

        $rules = [];

        foreach ($this->registry->classesForNamespace($namespace) as $settingsClass) {
            foreach (ValidateInput::rulesFor($settingsClass) as $property => $rule) {
                $rules[$settingsClass::publicKey() . '.' . $property] = $rule;
            }
        }

        return $rules;
    }
}
