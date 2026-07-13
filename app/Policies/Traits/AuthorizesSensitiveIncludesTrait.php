<?php

declare(strict_types=1);

namespace App\Policies\Traits;

use App\Policies\Contracts\DefinesSensitiveIncludes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Authorizes sensitive relationship include paths requested via the JSON:API
 * `?include` query parameter. The framework only authorises dedicated
 * related/relationship URLs, not the `?include` query parameter, so a viewer
 * who can `view` a model would otherwise receive privileged children inline.
 *
 * Each requested include root is checked against the model's policy: when the
 * policy {@see DefinesSensitiveIncludes declares a field as sensitive}, the
 * corresponding `view{Field}` ability is authorized against the model. Models
 * whose policy does not implement the interface are passed through untouched.
 *
 * Intended for use in controllers (and other request-scoped classes) where the
 * `?include` query parameter is available.
 */
trait AuthorizesSensitiveIncludesTrait
{
    /**
     * @param object $model the route-bound model whose policy gates the includes
     * @param list<string> $includeRoots top-level include paths requested via `?include`
     */
    protected function authorizeSensitiveIncludes(object $model, array $includeRoots): void
    {
        $policy = Gate::getPolicyFor($model);

        if (!$policy instanceof DefinesSensitiveIncludes) {
            return;
        }

        $sensitive = $policy->sensitiveIncludes();

        foreach ($includeRoots as $field) {
            if (\in_array($field, $sensitive, true)) {
                Gate::authorize('view' . Str::studly($field), $model);
            }
        }
    }
}
