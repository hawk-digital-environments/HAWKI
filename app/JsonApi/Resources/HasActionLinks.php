<?php

declare(strict_types=1);

namespace App\JsonApi\Resources;

use Illuminate\Support\Facades\Gate;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;

trait HasActionLinks
{
    protected function actionLinks($request): Links
    {
        $links = new Links();

        if ($self = $this->selfLink()) {
            $links->push($self);
        }

        $base = $this->selfUrl();
        $user = $request?->user();

        if ($base && $user) {
            $gate = Gate::forUser($user);

            foreach ($this->resolveCrudAbilityNames() as $ability) {
                $response = $gate->inspect($ability, $this->resource);
                $links->push(new Link(
                    $ability,
                    $base,
                    ['message' => $response->allowed() ? 'ALLOWED' : 'DENIED'],
                ));
            }

            foreach ($this->resolveActions() as $action) {
                $response = $gate->inspect($action['method'], $this->resource);
                $links->push(new Link(
                    $action['method'],
                    $base . '/actions/' . $action['segment'],
                    ['message' => $response->allowed() ? 'ALLOWED' : 'DENIED'],
                ));
            }
        }

        return $links;
    }

    protected function resolveActions(): iterable
    {
        $resourceType = $this->schema::type();

        /** @var iterable<\Illuminate\Routing\Route> $routes */
        $routes = app('router')->getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();
            $name = $route->getName();

            if (
                \is_string($name)
                && \is_string($uri)
                && str_contains($name, ".{$resourceType}.")
                && str_contains($uri, '{')
            ) {
                $method = $route->getActionMethod();

                if (!\is_string($method) || 'Closure' === $method) {
                    continue;
                }

                // Action routes may use '/actions/' prefix or direct '/{id}/action'.
                if (str_contains($uri, '/actions/')) {
                    $after = mb_substr($uri, mb_strrpos($uri, '/actions/') + mb_strlen('/actions/'));
                } else {
                    $after = mb_substr($uri, mb_strrpos($uri, '/{') + 1);
                    $after = mb_substr($after, mb_strpos($after, '}') + 2);
                }
                $segment = mb_strstr($after, '{', true) ?: $after;

                yield ['method' => $method, 'segment' => $segment];
            }
        }
    }

    protected function resolveCrudAbilityNames(): iterable
    {
        yield 'update';

        yield 'delete';
    }
}
