# API Stability

The `@api` marker is HAWKI's contract for code that other code — including future plugins — can depend on. Read this before adding a class others will depend on.

## The `@api` contract

A class or method marked with `@api` in its DocBlock is part of the **stable public surface**:

- The signature will not change before the next major version.
- Removal requires a `@deprecated` tag first, with the target version and migration path.

Everything **without** `@api` is internal. It may change at any time and must not be depended on from outside its domain.

```php
/**
 * @api
 */
class AiService
{
    /**
     * @api
     * @deprecated Will be removed in v3.0. Use getAvailableModels() instead.
     */
    public function getModels(): AiModelCollection { ... }
}
```

If a class carries `@api` but none of its methods do, the entire public and protected surface is considered stable.

## `@api` classes are never `final`

`@api` classes and methods are intentionally not `final`. HAWKI is designed with a plugin system in mind — `@api` classes must remain open for decoration and extension via `$app->extend()`. Internal (non-`@api`) classes may be `final` with no stability guarantee.

## Rules per kind

- **Events marked `@api`** use `public readonly` properties and past-tense names.
- **Eloquent models marked `@api`** declare their stable surface via class-level `@property` docblocks and public relationship methods. Private implementation details remain internal even on `@api` classes.
- **`...Service` classes** are the public API of their domain and must carry `@api` (see [Layers & Domains](./100-Layers-and-Domains.md)).

## Decorating an `@api` service

To wrap an `@api` service without modifying core code, use `App\Utils\DecoratorTrait` together with Laravel's `$app->extend()`. The trait uses reflection to copy all constructor-injected properties from the original instance into your decorator, so you only override the methods you care about.

```php
class DecoratedAiService extends AiService
{
    use DecoratorTrait;

    public function getModels(): AiModelCollection
    {
        $models = parent::getModels();
        return $this->filterByPolicy($models);
    }
}

// In a ServiceProvider
$this->app->extend(AiService::class, function (AiService $original) {
    return DecoratedAiService::createDecoratedOf($original);
});
```

`DecoratorTrait` is the designated mechanism for plugin authors to customise service behaviour.

:::caution[Reflection caveat]
`DecoratorTrait` copies public and protected properties. It cannot copy private properties, so decorators that rely on private internals are not safe. Call only `public` and `protected` methods from your decorator.
:::

## Where the live extension points are

The list of registries, container tags, filter events, and other hooks you can use today is in [Extending HAWKI](./220-Extending-HAWKI.md). The not-yet-implemented v3 plugin system (`HawkiPluginInterface`, `PluginRegistry`, Composer lifecycle) will build on these extension points.
