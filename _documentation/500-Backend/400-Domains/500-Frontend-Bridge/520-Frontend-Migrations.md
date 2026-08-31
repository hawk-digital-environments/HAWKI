# Frontend Migrations

HAWKI encrypts user data in the browser. That means the server cannot read, transform, or re-encrypt user content. When a data schema change requires transforming that encrypted content, a standard Laravel database migration cannot help — the server does not have the keys.

Frontend migrations solve this by splitting each migration into two cooperating files: a PHP file that tracks which users need the migration and optionally collects server-visible context, and a TypeScript file that runs in the user's browser at the right moment and performs the actual data transformation.

For the frontend side of the system — the JS migration runner, run types, the plugin `migrations()` hook, and `MigrationContext` — see [Frontend Migrations (Concepts)](../../../600-Frontend/200-Concepts/180-Frontend-Migrations.md). This page covers the backend half.

## Scaffolding

```bash
php artisan make:frontend-migration <name>
```

The command prompts for the plugin that owns the JS migration (scanning `resources/js/plugins/`), then for the run type. It creates:

- `database/migrations/{timestamp}_{runType}_{name}.php` — the PHP side.
- `resources/js/plugins/{plugin}/migrations/{runType}/{timestamp}_{runType}_{name}.ts` — the TS side.
- Updates the selected plugin's `.plugin.ts` to register the migration via `migrations()`.

See the frontend concepts page for the full prompt flow and the plugin hook.

## PHP side

The generated PHP migration is an anonymous class following the standard Laravel migration shape. Its `up()` calls the `FrontendMigrator` facade (backed by `FrontendMigrationBuilder`) instead of modifying a schema, and `down()` always throws:

```php
use App\Models\User;
use App\Services\Frontend\Migrations\Exceptions\NoDownForFrontendMigrationsExceptionException;
use App\Services\Frontend\Migrations\Facades\FrontendMigrator;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        FrontendMigrator::register(__FILE__, static function (User $user, Connection $connection): array|null {
            // Collect server-visible context the JS migration will receive as ctx.data.
            // Return null to skip this user (they get ctx.data = undefined).
            return ['someKey' => $user->some_value];
        });
    }

    public function down(): void
    {
        throw NoDownForFrontendMigrationsExceptionException::forMigration(__CLASS__);
    }
};
```

`FrontendMigrator::register()` takes the migration name (pass `__FILE__` — the filename stem is used as the stored name) and an optional `$userDataFinder` closure with the signature `Closure(User, Connection): array|null`. The closure runs once per existing user during `php artisan migrate`. Its return value is serialised and stored encrypted in `frontend_migration_userdata`; this is what the JS migration receives as `ctx.data`. Return `null` or `false` to skip a user. The entire `register()` call runs inside a transaction.

If you omit the closure, the migration is still registered but every user receives `ctx.data = undefined`.

### Run type is not a `register()` parameter

The run type (`after_login` / `after_passkey` / custom) is **not** passed to `FrontendMigrator::register()`. It is inferred entirely on the frontend from the JS file's directory (see [Frontend Migrations (Concepts)](../../../600-Frontend/200-Concepts/180-Frontend-Migrations.md#run-types)). The `FrontendMigrationRunType` enum (`AFTER_LOGIN`, `AFTER_PASSKEY`) exists for naming and documentation; the backend does not store or dispatch on it.

### Why `down()` always throws

Frontend migrations are **intentionally irreversible**. The server cannot re-encrypt data it has never read. If a migration re-encrypts keychain values or rewrites room keys, there is no safe way to undo that without the user's passkey — which the server never holds. `down()` must always throw `NoDownForFrontendMigrationsExceptionException`. If a migration turns out to be wrong, write a new forward migration that corrects the result.

### Real-World Example

`database/migrations/2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values.php` registers the keychain-format migration, collects each user's legacy encrypted blob in the same step, then drops the old table:

```php
public function up(): void
{
    FrontendMigrator::register(__FILE__, static function (User $user, Connection $connection): array|null {
        $userdata = $connection->table('private_user_data')
            ->select()
            ->where('user_id', $user->id)
            ->first();

        if (!$userdata) {
            return null;
        }

        return [
            'blob' => (string)EncryptionUtils::symmetricCryptoValueFromStrings(
                $userdata->KCIV,
                $userdata->KCTAG,
                $userdata->keychain,
            ),
        ];
    });

    Schema::drop('private_user_data');
}
```

## API Surface

The frontend discovers and reports migrations through two JSON:API endpoints under `/api/hawki/v1`:

### Connection bootstrap

`GET /api/hawki/v1/connections/hawki` includes a `migrations_to_apply` integer count when the session is an authenticated native session. `ConnectionFactory` populates it by calling `FrontendMigrationRepository::findAllMigrationsToApplyForUser($user)->count()`. The frontend uses this to short-circuit `app.migration.hasPending` without a second round-trip.

### Migrations resource

`GET /api/hawki/v1/migrations` returns the pending migrations for the authenticated user as `MigrationToApply` value objects. The schema (`MigrationSchema`) exposes two attributes:

| Attribute | Description |
|---|---|
| `id` | The migration name (the Laravel migration's filename stem) — used by the frontend to locate the TS module |
| `data` | The decrypted per-user payload, or `null` if no `userDataFinder` was registered for this migration |

The run type is **not** part of the response — it is inferred from the JS file path on the frontend.

### Marking a migration applied

`POST /api/hawki/v1/migrations/actions/apply` (controller action `markMigrationAsApplied`) takes a `migration_name` body. It:

1. Looks up the `FrontendMigration` by name (404 if unknown).
2. Deletes the user's `frontend_migration_userdata` row for that migration (the payload is no longer needed).
3. Inserts an `applied_frontend_migrations` row via `AppliedFrontendMigrationRepository::applyForUser()` — idempotent: if a row already exists it returns the existing record.

## Database Tables

| Table | Purpose |
|---|---|
| `frontend_migrations` | One row per registered migration (`migration_name`, `has_userdata`). Written by `FrontendMigrationBuilder::register()`. |
| `frontend_migration_userdata` | Per-user encrypted payload for migrations that collected context. Dropped per-user once the migration is marked applied. |
| `applied_frontend_migrations` | One row per (user, migration) pair that has already run in the browser. Acts as the "done" ledger. |

New users are bulk-marked as having applied all currently-registered migrations by `AppliedFrontendMigrationRepository::applyAllForNewUser()` at registration time, so they never receive migrations that predate their account.

## Lifecycle

```
User logs in
     │
     ▼
GET /api/hawki/v1/connections/hawki
  └─ migrations_to_apply: N  ◄── ConnectionFactory counts pending migrations
     │
     ▼ (if N > 0)
GET /api/hawki/v1/migrations
  └─ Returns MigrationToApply objects (id = migration name, data = per-user payload)
     │
     ├─ after_login migrations run immediately
     │
     ▼ (user enters passkey)
  after_passkey migrations run
     │
     ▼
POST /api/hawki/v1/migrations/actions/apply
  └─ Inserts applied_frontend_migrations record for this user
  └─ Deletes the frontend_migration_userdata row for this user
```

## Retry behaviour and idempotency

The `applied_frontend_migrations` record is only written on a successful `POST .../actions/apply`. If the JS migration throws or the user closes the browser before completing it, the migration will be retried on the next login. Write migrations to be idempotent: running them twice on the same data must produce the same result as running them once.
