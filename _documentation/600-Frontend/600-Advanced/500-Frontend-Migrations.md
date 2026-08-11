# Frontend Migrations

Frontend migrations are one-time JavaScript scripts that run in the user's browser to transform or re-key locally stored or encrypted data. They are necessary when encryption formats change or user data structures are updated in ways that cannot be handled server-side — typically because the server never has access to the plaintext.

Common triggers include passkey format changes, re-encryption of room or conversation keys, and transformation of locally-held key material.

## How They Work

The system spans both the PHP backend and the TypeScript frontend. A single logical migration consists of a Laravel database migration (which registers the migration and builds per-user payloads) and a corresponding JS file (which performs the actual in-browser transformation).

The frontend side is owned by `MigrationExtension` (`kernel/migrations/MigrationExtension.ts`, exposed as `app.migration`) and driven by the core plugin: `CorePlugin.migrations()` hands the migration-file glob to the `MigrationRegistrar`. The runner `applyMigrations(runType)` is exported from `kernel/migrations/helpers.ts` and published to legacy code as `window.applyMigrations`.

### End-to-End Flow

1. A developer runs `php artisan make:frontend-migration` to scaffold both a Laravel database migration and a JS migration file.
2. When the Laravel migration runs (e.g., during `php artisan migrate`), it calls `FrontendMigrationBuilder::register()`, which inserts a record into the database and optionally pre-computes a per-user data payload for every existing user.
3. After the user authenticates, the server includes a `migrations_to_apply` count in the connection response. The JS side calls `hasPendingMigrations()` to check this value.
4. `applyMigrations(runType)` is called with the appropriate run type. It fetches the list of pending migration names and their payloads from the `migrations` API endpoint, then iterates over them:
   - Each migration is matched by name against the in-memory registry built from the core plugin's `migrations()` glob.
   - Migrations whose `runType` does not match the current call are skipped.
   - The matching JS module is loaded and its `migrate(ctx)` function is called.
   - If `migrate()` resolves successfully, a `POST` to `actions/apply` marks the migration done on the server.
5. If `migrate()` throws, the user sees an alert and the error propagates. The migration will be retried on the next login.

### Error Handling

A failed migration is **not** marked as applied on the server, so it will be attempted again the next time `applyMigrations` is called for that run type. Write migrations to be idempotent where possible — guard against already-migrated state at the start of the function.

## Run Types

Migrations are grouped by when they should execute:

| Run type | When it runs |
|---|---|
| `after_login` | Default — as soon as the user authenticates |
| `after_passkey` | After the user verifies their passkey (required when the migration needs key material) |
| custom string | Any identifier — callers must trigger `applyMigrations('myType')` manually |

The run type is inferred from the **directory** the JS file lives in under `resources/js/plugins/core/migrations/`:

- Files directly in `migrations/` → `after_login`
- Files in `migrations/after_passkey/` → `after_passkey`
- Files in `migrations/my_type/` → `my_type`

No configuration is needed — the directory structure is read at build time via the glob handed to the `MigrationRegistrar` by the core plugin.

:::info[Core plugins only]
Migrations are a `HawkiCorePlugin` hook (`plugin.migrations()`), not part of the third-party `HawkiPlugin` contract. Only built-in plugins can register migrations. See [Writing a Plugin](130-Writing-a-Plugin.md).
:::

## Creating a New Migration

Run the artisan command:

```bash
php artisan make:frontend-migration your_migration_name
```

The name is converted to `snake_case`. The command then prompts for the run type:

```
When should the JS migration run?
  [after_login]   After user login
  [after_passkey] After passkey verification
  [custom]        Custom (you will need to manually run the migration)
```

Selecting `custom` opens a second prompt for the exact identifier string.

The command creates two files and prints their paths:

```
Frontend migration created successfully.
Backend migration: database/migrations/2026_xx_xx_xxxxxx_create_frontend_migration_your_migration_name.php
JS migration:      resources/js/plugins/core/migrations/after_passkey/2026_xx_xx_xxxxxx_your_migration_name.ts
```

Run `php artisan migrate` after creation so the backend records the migration and builds any per-user payloads.

## Writing the JS Migration File

Every migration file must export a single async `migrate` function. The `MigrationContext` provides the run type, the migration name, the fully-assembled `HawkiApp` (so migrators can reach config, stores, the REST API, the keychain), and the optional server-provided payload:

```ts
import type {MigrationContext} from '$lib/kernel/migrations/MigrationExtension.js';

export async function migrate({name, data, app}: MigrationContext): Promise<void> {
    // New users may have no legacy data — always guard before accessing data.
    if (!data) {
        return;
    }

    // Transform data...
    // Use app.config.get(), app.stores.get('keychain'), app.restApi, etc.
    // Use encryption helpers from '$lib/kernel/encryption/...' if needed.
}
```

The `MigrationContext` fields:

| Property | Type | Description |
|---|---|---|
| `runType` | `string` | The run type this migration is executing under |
| `name` | `string` | The migration name (matches the filename without extension) |
| `app` | `HawkiApp` | The fully-assembled app — reach config, stores, restApi, the keychain handle through it |
| `data` | `any \| undefined` | The per-user payload built by the backend closure; `undefined` if no `userDataFinder` was registered or the finder returned `null`/`false` for this user |

### Real-World Example

`2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values.ts` migrates the legacy flat keychain blob (a single AES-GCM-encrypted JSON object stored on the server) to the new per-key keychain format.

The migration:

1. Returns early if `data.blob` is absent — this means the user was created after the new keychain system was introduced and has nothing to migrate.
2. Derives the keychain encryption key from the user's passkey (`oldUiBridge.passkey`, available at `after_passkey` run time) and the `userdata` salt from `app.config.get().salts`.
3. Decrypts and parses the legacy blob.
4. Iterates over the decrypted keys, re-imports each `CryptoKey` from the legacy JWK export format, and writes them into the new keychain using `createKeychainHandle(app, …).doUpdate()`.

The `after_passkey` run type is essential here because the passkey is required to derive the decryption key — it is not available at `after_login` time.

## Registering the Backend Migration

The generated Laravel migration file calls `FrontendMigrationBuilder::register()`. The optional `userDataFinder` closure runs once per existing user during `php artisan migrate` and returns the array that becomes `ctx.data` in the JS migration. Return `null` or `false` to skip a user (they will receive `ctx.data = undefined`).

```php
use App\Services\Frontend\Migrations\FrontendMigrationBuilder;
use App\Models\User;
use Illuminate\Database\Connection;

app(FrontendMigrationBuilder::class)->register(
    migrationName: '2026_xx_xx_xxxxxx_your_migration_name',
    userDataFinder: function (User $user, Connection $db): array|null {
        $blob = $db->table('user_keychain')
            ->where('user_id', $user->id)
            ->value('blob');

        return $blob ? ['blob' => $blob] : null;
    }
);
```

If no `userDataFinder` is provided, the migration is still registered but every user receives `ctx.data = undefined`.

The `userDataFinder` closure receives:

| Parameter | Type | Description |
|---|---|---|
| `$user` | `User` | The Eloquent user model |
| `$db` | `Connection` | The active database connection |

The entire `register()` call runs inside a transaction — inserting the migration record and all per-user data rows is atomic.

## Auto-Discovery

The core plugin's `migrations()` hook lazy-globs every `*.ts` file under `resources/js/plugins/core/migrations/` and hands the loaders to the `MigrationRegistrar`. The run type and migration name are inferred from the file path — no manual import or registration is required in JavaScript. Adding a new file to the correct directory is sufficient for it to be picked up.
