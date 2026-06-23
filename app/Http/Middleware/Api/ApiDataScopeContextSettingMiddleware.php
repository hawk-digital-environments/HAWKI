<?php

namespace App\Http\Middleware\Api;

use App\Services\System\Container\ServiceLocator;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Server\Server;

readonly class ApiDataScopeContextSettingMiddleware
{
    public const string NO_SCOPE_PARAM = 'no_scope';

    public function __construct(
        // Use a service locator here, because otherwise we end up getting errors about not being able to create the Server instance
        // This is because the server is only bound by another middleware and therefore when the stack is resolved,
        // the server is not yet available in the container.
        private ServiceLocator $serviceLocator
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $apiServer = $this->serviceLocator->get(Server::class);
        } catch (\Throwable) {
            return $next($request);
        }

        $this->populateContextByRequest($request, $apiServer);
        return $next($request);
    }

    private function populateContextByRequest(Request $request, Server $apiServer): void
    {
        $noScope = $request->query(self::NO_SCOPE_PARAM);
        if (!is_array($noScope)) {
            return;
        }

        $findMostSimilar = static fn(string $input, array $options): string => collect($options)->sortBy(fn(string $item) => levenshtein($input, $item))->first();

        $schemaContainer = $apiServer->schemas();
        foreach ($noScope as $resourceType => $scopeKey) {
            if (!is_string($resourceType)) {
                abort(400, sprintf('Invalid resource type in "%s" query parameter. Expected string keys in format no_scope[resource-type]=scopeKey', self::NO_SCOPE_PARAM));
            }

            if (!$schemaContainer->exists($resourceType)) {
                $closestResourceType = $findMostSimilar($resourceType, $schemaContainer->types());
                abort(400, sprintf('Resource type "%s" in "%s" query parameter does not exist. Did you mean "%s"?', $resourceType, self::NO_SCOPE_PARAM, $closestResourceType));
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $schemaContainer->schemaFor($resourceType)::model();
            // Fail if the model class does not exist or is not an Eloquent model
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                abort(400, sprintf('Resource "%s" is not bound to an eloquent model.', $resourceType));
            }

            // Boot the model class, so the scopes are registered and available in the context.
            if (!is_callable([$modelClass, 'getContextualScopes']) || !method_exists($modelClass, 'getContextualScopes')) {
                abort(400, sprintf('Resource "%s" does not use contextual scopes you could disable.', $resourceType));
            }

            $availableScopes = $modelClass::getContextualScopes();
            if ($scopeKey === '*') {
                foreach ($availableScopes as $scope) {
                    $scope->disable();
                }
                continue;
            }

            if (is_string($scopeKey) && str_contains($scopeKey, ',')) {
                $scopeKey = collect(explode(',', $scopeKey))->map(static fn(string $item) => trim($item))->filter()->values()->all();
            }
            $scopeKeys = is_array($scopeKey) ? $scopeKey : [$scopeKey];

            foreach ($scopeKeys as $key) {
                if (!is_string($key)) {
                    abort(400, sprintf('Invalid scope key for resource type "%s" in "%s" query parameter. Expected string values.', $resourceType, self::NO_SCOPE_PARAM));
                }
                if (!isset($availableScopes[$key])) {
                    $closestScopeKey = $findMostSimilar($key, array_keys($availableScopes));
                    abort(400, sprintf('Scope key "%s" for resource type "%s" in "%s" query parameter does not exist. Did you mean "%s"?', $key, $resourceType, self::NO_SCOPE_PARAM, $closestScopeKey));
                }
                $availableScopes[$key]->disable();
            }
        }
    }
}
