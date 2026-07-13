<?php

declare(strict_types=1);

namespace App\JsonApi\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;

trait HasActionLinks
{

    /** @var array<string, array<int, array<string, string>>> */
    protected static array $resolvedActionsCache = [];

    /**
     * The action Links for a resource
     * @param Request $request
     * @return Links
     */
    protected function actionLinks(Request $request): Links
    {
        $links = new Links();

        if ($self = $this->selfLink()) {
            $links->push($self);
        }

        $base = $this->selfUrl();
        $user = $request->user();

        if ($base && $user) {
            $gate = Gate::forUser($user);

            foreach ($this->resolveCrudAbilityNames() as $key => $method) {
                $response = $gate->inspect($key, $this->resource);
                $links->push(new Link(
                    $key,
                    $base,
                    [
                        'message' => $response->allowed() ? 'ALLOWED' : 'DENIED',
                        'method' => $method
                    ],
                ));
            }

            foreach ($this->resolveActions() as $action) {
                $response = $gate->inspect($action['ability'], $this->resource);
                $links->push(new Link(
                    $action['key'],
                    $base . '/' . $action['suffix'],
                    [
                        'message' => $response->allowed() ? 'ALLOWED' : 'DENIED',
                        'method' => $action['method'],
                    ],
                ));
            }
        }

        return $links;
    }

    /**
     * Resolve action links from the router.
     *
     * Two URL shapes are handled:
     *  - /actions/{segment}    custom controller actions; the link key and the
     *                          policy ability are both the controller method
     *                          name (e.g. addFavorite), and the HTTP method is
     *                          read from the route declaration.
     *  - /relationships/{rel}  JSON:API relationship endpoints; one link is
     *                          emitted per relation + verb, keyed by the
     *                          matching policy ability (e.g. detachSharedUsers)
     *                          and carrying the verb's HTTP method.
     *
     * Other routes under the resource (e.g. related-GET "/{id}/{relation}") are
     * intentionally ignored: they are not actions and previously collapsed into
     * a single bogus link per method name.
     *
     * @return iterable<array<string, string>>
     */
    protected function resolveActions(): iterable
    {
        $resourceType = $this->schema::type();

        /** @var iterable<\Illuminate\Routing\Route> $routes */
        $routes = app('router')->getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();
            $name = $route->getName();

            if (!\is_string($name) || !\is_string($uri)) {
                continue;
            }

            if (!str_contains($name, ".{$resourceType}.") || !str_contains($uri, '{')) {
                continue;
            }

            $method = $route->getActionMethod();

            if (!\is_string($method) || 'Closure' === $method) {
                continue;
            }

            // Custom actions registered under "/actions/".
            if (str_contains($uri, '/actions/')) {
                $after = mb_substr($uri, mb_strrpos($uri, '/actions/') + mb_strlen('/actions/'));
                $segment = mb_strstr($after, '{', true) ?: $after;

                yield [
                    'key' => $method,
                    'ability' => $method,
                    'suffix' => 'actions/' . $segment,
                    'method' => $this->routeHttpMethod($route),
                ];

                continue;
            }

            // JSON:API relationship endpoints: .../relationships/{relation}.
            if (str_contains($uri, '/relationships/')) {
                $resolved = $this->resolveRelationshipAction($name, $uri, $resourceType);

                if ($resolved !== null) {
                    yield $resolved;
                }
            }
        }
    }

    /**
     * Build a per-relation action link from a relationship route.
     *
     * The route name carries the canonical snake_case relation and the verb
     * ("v1.{resource}.{relation}.{verb}"), which maps cleanly to the policy
     * ability "{verbPrefix}{StudlyRelation}" (e.g. detachSharedUsers) and to
     * the JSON:API HTTP method for that verb.
     *
     * @return array<string, string>|null
     */
    protected function resolveRelationshipAction(string $name, string $uri, string $resourceType): ?array
    {
        $marker = ".{$resourceType}.";
        $pos = mb_strpos($name, $marker);

        if ($pos === false) {
            return null;
        }

        $tail = mb_substr($name, $pos + mb_strlen($marker));
        $parts = explode('.', $tail);
        $relation = $parts[0];
        $verb = $parts[1] ?? '';

        $verbMap = [
            'show' => ['ability' => 'view', 'method' => 'GET'],
            'update' => ['ability' => 'update', 'method' => 'PATCH'],
            'attach' => ['ability' => 'attach', 'method' => 'POST'],
            'detach' => ['ability' => 'detach', 'method' => 'DELETE'],
        ];

        if ($relation === '' || !isset($verbMap[$verb])) {
            return null;
        }

        $entry = $verbMap[$verb];
        $ability = $entry['ability'] . Str::studly($relation);

        $kebab = mb_substr($uri, mb_strrpos($uri, '/relationships/') + mb_strlen('/relationships/'));
        $kebab = mb_strstr($kebab, '/', true) ?: $kebab;

        return [
            'key' => $ability,
            'ability' => $ability,
            'suffix' => 'relationships/' . $kebab,
            'method' => $entry['method'],
        ];
    }

    /**
     * Resolve the HTTP verb for a route, stripping the implicit HEAD method
     * that Laravel adds to GET routes.
     */
    protected function routeHttpMethod(\Illuminate\Routing\Route $route): string
    {
        $methods = array_filter(
            $route->methods(),
            static fn (string $m): bool => $m !== 'HEAD',
        );

        $method = reset($methods);

        return \is_string($method) ? $method : 'GET';
    }

    protected function resolveCrudAbilityNames(): iterable
    {
        yield 'update' => 'PATCH';

        yield 'delete' => 'DELETE';
    }
}
