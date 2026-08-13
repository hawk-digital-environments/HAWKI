# Frontend Migrations

HAWKI encrypts user data in the browser. That means the server cannot read, transform, or re-encrypt user content. When a data schema change requires transforming that encrypted content, a standard Laravel database migration cannot help — the server does not have the keys.

Frontend migrations solve this by splitting each migration into two cooperating files: a PHP file that tracks which users need the migration and optionally collects server-visible context, and a TypeScript file that runs in the user's browser at the right moment and performs the actual data transformation.

## Scaffolding

```
php artisan make:frontend-migration <name>
```

Creates two files: `database/migrations/{timestamp}_{name}_frontend.php` (PHP side) and `resources/js/migrations/{timestamp}_{name}.ts` (TypeScript side).

## PHP side

The PHP migration file follows the standard Laravel migration shape, but `up()` calls `FrontendMigrator::register()` instead of modifying a database schema:

```php
use App\Services\Frontend\Migrations\Facades\FrontendMigrator;

class AddRoomKeysV2Frontend extends Migration
{
    public function up(): void
    {
        FrontendMigrator::register(
            migrationName: __FILE__,
            userDataFinder: function (User $user): array {
                // Return server-visible context that the JS migration receives as ctx.data.
                // Returning null skips this user (migration is applied immediately for them).
                return [
                    'roomSlugs' => $user->rooms()->pluck('slug')->all(),
                ];
            }
        );
    }

    public function down(): void
    {
        throw new \Exception('Frontend migrations cannot be reversed.');
    }
}
```

`FrontendMigrator::register()` takes a unique `migrationName` (pass `__FILE__` to use the filename) and a `userDataFinder` closure (`Closure(User): array|null`). The closure's return value is serialised and stored encrypted in `frontend_migration_userdata`. This is what the JS migration receives as `ctx.data`.

### Why `down()` always throws

Frontend migrations are **intentionally irreversible**. The server cannot re-encrypt data it has never read. If a migration re-encrypts keychain values or rewrites room keys, there is no safe way to undo that without the user's passkey — which the server never holds. `down()` must always throw. If a migration turns out to be wrong, write a new forward migration that corrects the result.

## Run types

`FrontendMigrationRunType` controls when the JS migration runs in the browser boot sequence:

- `AFTER_LOGIN` — immediately after the user's session is established. Use when the migration only needs the user session; no encrypted data access required.
- `AFTER_PASSKEY` — after the user has entered and verified their passkey. Use when the migration reads or transforms passkey-encrypted data (keychain contents, room keys).

Specify the run type as a parameter to `FrontendMigrator::register()` (defaults to `AFTER_LOGIN`).

## TypeScript side

The TypeScript migration file exports a default function:

```typescript
import type { MigrationContext } from '@/data/migrations/migrations';

export default async function migrate(ctx: MigrationContext): Promise<void> {
    const roomSlugs: string[] = ctx.data?.roomSlugs ?? [];
    for (const slug of roomSlugs) {
        await ctx.keychain.doUpdatesDeferred(() => {
            // Batch keychain batch-update calls here.
        });
    }
}
```

`MigrationContext` carries `data` (the deserialised return value of the PHP `userDataFinder` closure), `keychain` (the live `KeychainHandle` instance, giving access to decrypted keys and the `doUpdatesDeferred()` batching helper), and additional context from the connection bootstrap as needed.

`KeychainHandle.doUpdatesDeferred()` is specifically designed for migrations: it batches all `batch-update` calls and flushes them in a single round-trip to `POST /api/hawki/v1/user-keychain-values/actions/batch-update` at the end of the migration. See [User Keychain](../400-Encryption/420-User-Keychain.md) for the batch-update API.

## Lifecycle

```
User logs in
     │
     ▼
GET /api/hawki/v1/connections/hawki
  └─ migrationsToApply: N  ◄── ConnectionFactory counts pending migrations
     │
     ▼ (if N > 0)
GET /api/hawki/v1/migrations
  └─ Returns list of MigrationToApply objects (migration name + run type + ctx.data)
     │
     ├─ AFTER_LOGIN migrations run immediately
     │
     ▼ (user enters passkey)
  AFTER_PASSKEY migrations run
     │
     ▼
POST /api/hawki/v1/migrations/actions/apply
  └─ Writes applied_frontend_migrations record for this user
  └─ Deletes the frontend_migration_userdata row for this user
```

`ConnectionFactory` calls `FrontendMigrationRepository::findAllMigrationsToApplyForUser($user)->count()` to populate `migrationsToApply` in the connection bootstrap. See [Repositories](../../200-Concepts/130-Repositories.md) for the base pattern.

## Retry behaviour and idempotency

The `applied_frontend_migrations` record is only written on a successful `POST .../actions/apply`. If the JS migration throws or the user closes the browser before completing it, the migration will be retried on the next login. Write migrations to be idempotent: running them twice on the same data must produce the same result as running them once.
