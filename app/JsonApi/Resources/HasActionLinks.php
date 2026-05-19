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

            foreach ($this->resolveActionNames() as $action) {
                $response = $gate->inspect($action, $this->resource);
                $links->push(new Link(
                    $action,
                    $base . '/actions/' . $action,
                    ['message' => $response->allowed() ? 'ALLOWED' : 'DENIED'],
                ));
            }
        }

        return $links;
    }

    protected function resolveActionNames(): iterable
    {
        $resourceType = $this->schema::type();

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            $name = $route->getName();

            if (
                is_string($name)
                && is_string($uri)
                && str_contains($name, ".{$resourceType}.")
                && str_contains($uri, '/actions/')
                && str_contains($uri, '{')
            ) {
                $method = $route->getActionMethod();

                if (is_string($method) && $method !== 'Closure') {
                    yield $method;
                }
            }
        }
    }
}
