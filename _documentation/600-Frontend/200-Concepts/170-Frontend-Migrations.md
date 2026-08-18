# Frontend Migrations

Frontend migrations are one-time JavaScript scripts that run in the user's browser to transform or re-key locally stored or encrypted data. They are necessary when encryption formats change or user data structures are updated in ways that cannot be handled server-side — typically because the server never has access to the plaintext.

Common triggers include passkey format changes, re-encryption of room or conversation keys, and transformation of locally-held key material.

:::warning[Dragons — core plugins only]
Frontend migrations have an unsolved rollback problem. A migration runs **deferred** — only after the user logs in (and, for `after_passkey`, after they unlock). If a plugin that registered a migration is later uninstalled, its migrations would need to roll back, but the migration source may no longer be present. The only way to handle this would be to serialize the PHP and JS migration code into the database so the rollback survives the plugin's removal — but that means a rollback could fail when the code it references has since changed. There is no clean solution yet.

For this reason **migrations are restricted to core plugins** (`HawkiCorePlugin`, not `HawkiPlugin`). Do not introduce migration registration on third-party plugins until the rollback story is settled. See [Technical Debt](../900-Technical-Debt.md).
:::

## How They Work

The system spans both the PHP backend and the TypeScript frontend. A single logical migration consists of a Laravel database migration (which registers the migration and builds per-user payloads) and a corresponding JS file (which performs the actual in-browser transformation).

The frontend side is owned by `MigrationExtension` (`kernel/migrations/MigrationExtension.ts`, exposed as `app.migration`) and driven by the core plugin: `CorePlugin.migrations()` hands the migration-file glob to the `MigrationRegistrar`.

### End-to-End Flow

1. A developer runs `php artisan make:frontend-migration` to scaffold both a Laravel database migration and a JS migration file. The command prompts for the plugin that owns the migration (scanning `resources/js/plugins/`), then for the run type. It writes the JS file into the selected plugin's `migrations/` directory and ensures the plugin's `.plugin.ts` registers it — see [Creating a New Migration](#creating-a-new-migration).
2. When the Laravel migration runs (e.g., during `php artisan migrate`), it calls `FrontendMigrator::register()`, which inserts a record into the database and optionally pre-computes a per-user data payload for every existing user.
3. After the user authenticates, the server includes a `migrations_to_apply` count in the connection response. The JS side checks this via `app.migration.hasPending`.
4. `app.migration.apply(runType)` is called with the appropriate run type. It fetches the list of pending migration names and their payloads from the `migrations` API endpoint, then iterates over them:
   - Each migration is matched by name against the in-memory registry built from the plugin's `migrations()` glob.
   - Migrations whose `runType` does not match the current call are skipped.
   - The matching JS module is loaded and its `migrate(ctx)` function is called.
   - If `migrate()` resolves successfully, a `POST` to `actions/apply` marks the migration done on the server.
5. If `migrate()` throws, the user sees an alert and the error propagates. The migration will be retried on the next login.

### Error Handling

A failed migration is **not** marked as applied on the server, so it will be attempted again the next time `apply(runType)` is called for that run type. Write migrations to be idempotent where possible — guard against already-migrated state at the start of the function.

## Run Types

Migrations are grouped by when they should execute:

| Run type | When it runs |
|---|---|
| `after_login` | Default — as soon as the user authenticates |
| `after_passkey` | After the user verifies their passkey (required when the migration needs key material) |
| custom string | Any identifier — callers must trigger `apply('myType')` manually |

The run type is inferred from the **directory** the JS file lives in under `resources/js/plugins/{plugin}/migrations/`:

- Files directly in `migrations/` → `after_login`
- Files in `migrations/after_passkey/` → `after_passkey`
- Files in `migrations/my_type/` → `my_type`

No configuration is needed — the directory structure is read at build time via the glob handed to the `MigrationRegistrar` by the plugin's `migrations()` hook.

:::info[Core plugins only]
Migrations are a `HawkiCorePlugin` hook (`plugin.migrations()`), not part of the third-party `HawkiPlugin` contract. Only built-in plugins can register migrations. See [Extending HAWKI](../../700-Extending-Hawki/index.md).
:::

## Creating a New Migration

Run the artisan command:

```bash
php artisan make:frontend-migration your_migration_name
```

The name is converted to `snake_case`. The command then prompts for the plugin that should own the migration — it scans `resources/js/plugins/` and lists every directory containing a `*.plugin.ts` file:

```
Which plugin should the JS migration belong to?
  [core]
  [my_other_plugin]
```

Next it prompts for the run type:

```
When should the JS migration run?
  [after_login]   After user login
  [after_passkey] After passkey verification
  [custom]        Custom (you will need to manually run the migration)
```

Selecting `custom` opens a second prompt for the exact identifier string.

The command creates three things and prints their paths:

```
Frontend migration created successfully.
Backend migration: database/migrations/2026_xx_xx_xxxxxx_after_passkey_your_migration_name.php
JS migration:      resources/js/plugins/core/migrations/after_passkey/2026_xx_xx_xxxxxx_after_passkey_your_migration_name.ts
Plugin file:        resources/js/plugins/core/core.plugin.ts (already configured)
```

The command also ensures the selected plugin's `.plugin.ts` file has a `migrations()` hook that globs its migrations directory (see [Registering Migrations in a Plugin](#registering-migrations-in-a-plugin)). If the hook is already present it reports `already configured`; otherwise it adds the hook, the `MigrationRegistrar` import, and — if needed — upgrades the class from `HawkiPlugin` to `HawkiCorePlugin`.

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

The backend half of a frontend migration **must live inside a Laravel database migration** — never in a service provider, a boot hook, or anywhere else. The `make:frontend-migration` command generates this migration file for you, pre-wired with a call to the `FrontendMigrator` facade and a `down()` that always throws (frontend migrations cannot be rolled back — see the dragons warning above).

The facade is `App\Services\Frontend\Migrations\Facades\FrontendMigrator`, backed by `FrontendMigrationBuilder`. Its `register()` method takes the migration file (pass `__FILE__`) and an optional `$userDataFinder` closure that runs once per existing user during `php artisan migrate`. The array the closure returns becomes `ctx.data` in the JS migration; return `null` or `false` to skip a user (they receive `ctx.data = undefined`).

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
            // Collect the current per-user state the JS migration will receive.
            // Return null/false to skip this user.
            return ['someKey' => $user->some_value];
        });
    }

    public function down(): void
    {
        throw NoDownForFrontendMigrationsExceptionException::forMigration(__CLASS__);
    }
};
```

If you remove the `$userDataFinder` closure, the migration is still registered but every user receives `ctx.data = undefined`.

The `$userDataFinder` closure receives:

| Parameter | Type | Description |
|---|---|---|
| `$user` | `User` | The Eloquent user model |
| `$db` | `Connection` | The active database connection |

The entire `register()` call runs inside a transaction — inserting the migration record and all per-user data rows is atomic.

### Real-World Backend Example

`database/migrations/2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values.php` registers the keychain-format migration and collects each user's legacy encrypted blob in one step, then drops the old table:

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

Use this as the template whenever the migration needs to hand the JS side a snapshot of the user's current server-side state before transforming it.

## Registering Migrations in a Plugin

Migrations are registered through a core plugin's `migrations()` lifecycle hook, which receives a `MigrationRegistrar`. There are two ways to populate it.

### Option 1 — Module loading helper (recommended)

Let the registrar glob the plugin's migrations directory. Every `*.ts` file under the glob is picked up automatically; the migration name is inferred from the filename stem and the run type from the parent directory. This is what the `make:frontend-migration` command wires into the plugin file:

```ts
import type {HawkiCorePlugin} from '$lib/kernel/plugins/types.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';

export default class MyPlugin implements HawkiCorePlugin {
    readonly name = 'my_plugin';

    public migrations(registrar: MigrationRegistrar): void | Promise<void> {
        registrar.addFromModules(
            import.meta.glob('$lib/plugins/my_plugin/migrations/**/*.ts', {eager: false}),
        );
    }
}
```

Adding a new file under `plugins/my_plugin/migrations/{runType}/` is then the only step needed on the JS side — no manual import or registration call.

### Option 2 — Manual registration

For cases where you want explicit control over which migrations are registered (e.g. a migration loaded from a package outside the glob), call `registrar.add()` directly with the migration name, run type, and a lazy loader:

```ts
import type {HawkiCorePlugin} from '$lib/kernel/plugins/types.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';

export default class MyPlugin implements HawkiCorePlugin {
    readonly name = 'my_plugin';

    public migrations(registrar: MigrationRegistrar): void | Promise<void> {
        registrar.add(
            '2026_01_01_120000_after_login_update_user_prefs',
            'after_login',
            () => import('$lib/plugins/my_plugin/migrations/after_login/2026_01_01_120000_after_login_update_user_prefs.js'),
        );
    }
}
```

The name passed to `add()` must match the migration name stored by the backend (the Laravel migration's filename stem). `add()` throws on duplicate names, so collisions surface immediately.
