# Repositories

Repositories are the only place DB access happens. Services and controllers must never call Eloquent model statics (`Model::where(...)`, `Model::find(...)`) — they delegate to a repository. This keeps queries mockable and the service layer testable without a database.

## When to use a repository

Every Eloquent model that is read or written from a service or controller gets a repository. The repository lives in `{Domain}/Repositories/` and extends `AbstractRepository` (or `AbstractRepositoryWithContextualScopes` when the model uses contextual scopes).

## How to implement one

```php
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class UserRepository extends AbstractRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->getQuery()->where('email', $email)->first();
    }
}
```

The model class is resolved automatically by `GuessesModelNameTrait` using three fallback strategies, tried in order:

1. **`#[UseModel]` attribute** on the repository class — takes priority over everything.
2. **`@extends` DocBlock annotation** — `@extends AbstractRepository<App\Models\MyModel>` (without braces in the generic).
3. **Repository class name** — strip the `Repository` suffix, look up `App\Models\{Name}`. For repositories inside `App\Services\{Domain}\Repositories`, the domain prefix is also tried (e.g. `App\Services\Ai\Repositories\AiModelRepository` resolves to `App\Models\Ai\AiModel`).

When none of these succeed, a `CannotGuessRepositoryModelException` is thrown instructing you to add `#[UseModel]`.

When the repository name does not match the model, use the `#[UseModel]` escape hatch:

```php
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(User::class)]
class AccountRepository extends AbstractRepository
{
    // ...
}
```

`#[Singleton]` is the default on `AbstractRepository`; the attribute is shown here for clarity. Override it only if you need a different binding.

## The base API

`AbstractRepository` provides:

- `findOne(mixed $id): ?Model`
- `findAll(): Collection`
- `findAllLazy(): LazyCollection`
- `getQuery(): Builder` — fresh query builder with all global scopes applied
- `getQueryWithoutAnyScopes(): Builder` — bypasses every global scope; for admin tooling and data migrations only

`getQuery()` is the one you call in custom methods. It returns a fresh builder every time.

## Models with contextual scopes

When the model uses `HasContextualScopesTrait` (see [Contextual Scopes](./140-Contextual-Scopes.md)), extend `AbstractRepositoryWithContextualScopes` instead. Its `findOne` / `findAll` / `findAllLazy` accept an optional `ScopeOverrides` argument so callers can disable a scope for a single query:

```php
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

class AiModelRepository extends AbstractRepositoryWithContextualScopes
{
    public function findAllForAdmin(): Collection
    {
        return $this->findAll($this->makeScopeOverrides('active_filter'));
    }
}
```

`makeScopeOverrides(true|array|string|null)` is a convenience factory: pass `'active_filter'` to disable one scope, `['active_filter', 'usage_type_filter']` for several, `true` for all, `null` for none.

For fine-grained control beyond disabling by key, `getQueryWithScopeContext(Closure)` runs the closure against the `ModelScopeContext` directly.

## How to test a service that uses a repository

Inject the repository into the service via constructor. In tests, replace it with a mock or an in-memory fake:

```php
$repo = $this->createMock(AiModelRepository::class);
$repo->method('findAll')->willReturn(collect([new AiModel(...)]));
$service = new AiService($repo, ...);
```

Because the service only calls repository methods (never `Model::where(...)`), it tests cleanly without a database.

## Dragons

- **Repositories must not contain business logic.** A repository issues queries and returns results. Orchestration, decisions, and side effects belong in the service.
- **Models are not injectable, repositories are.** Never inject `AiModel` into a service; inject `AiModelRepository`.
- **The existing `RoomService` violates the sub-service rule** by using traits to split itself. See [Technical Debt](../900-Technical-Debt.md). Do not copy that pattern; new code uses sub-services.

## IDE support

Repository methods like `findOne` / `findAll` return the generic `Model` base type, which means your IDE cannot autocomplete model-specific fields and relations. To fix this, run:

```bash
bin/env artisan dev:helper:repository
```

This generates IDE helper stubs that resolve the `@template` parameter on `AbstractRepository` to the concrete model class, so `findOne` returns `User` instead of `Model`, and the query builder gets full autocomplete for model fields and relations. The stubs are written to `vendor/_hawki_ide_helpers/` (or `vendor/_laravel_idea/` if the [Laravel Idea](https://laravel-idea.com/) PhpStorm plugin is installed — in that case the generated stubs also hook into the plugin's smart query builder helpers for field and relation autocomplete).

## Source

- `app/Services/System/Database/Eloquent/Repositories/AbstractRepository.php`
- `app/Services/System/Database/Eloquent/Repositories/AbstractRepositoryWithContextualScopes.php`
- `app/Services/System/Database/Eloquent/Repositories/Attributes/UseModel.php`
- `app/Services/System/Database/Eloquent/Repositories/Traits/GuessesModelNameTrait.php`
- `app/Console/Commands/Dev/GenerateRepositoryHelperCodeCommand.php`
