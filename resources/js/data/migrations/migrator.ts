import {getConnection} from '$lib/data/connection/connection.js';
import {getResourceCollectionFromApi, postToResourceAction} from '$lib/data/api/api.js';

export type MigrationRunType = string | ('after_login' | 'after_passkey');

export interface MigrationContext {
    runType: MigrationRunType;
    name: string;
    data?: any;
}

export type Migrator = (ctx: MigrationContext) => Promise<void>;
export type MigratorModule = { migrate: Migrator };
export type MigrationLoader = () => Promise<MigratorModule>;

const migrations: Array<{
    name: string;
    runType: MigrationRunType;
    migrationLoader: MigrationLoader;
}> = [];

/**
 * Discovers and registers all migration modules by globbing `resources/js/migrations/**\/*.ts`.
 *
 * Called once during bootstrap. The run type is inferred from the directory name
 * (e.g. files inside `after_passkey/` run with `runType = 'after_passkey'`).
 * Files directly inside `migrations/` default to `after_login`.
 *
 * Each migration file must export a `migrate(ctx: MigrationContext): Promise<void>` function.
 */
export function autoRegisterMigrations(): void {
    const glob = import.meta.glob('../../migrations/**/*.ts', {eager: false});
    const inferMigrationNameFromFileName = (filePath: string): string => {
        const match = filePath.match(/\/([^\/]+)\.ts$/);
        if (!match) {
            throw new Error(`Could not infer migration name from file path ${filePath}`);
        }
        return match[1];
    };
    const inferRunTypeFromFilePath = (filePath: string): MigrationRunType => {
        const pathParts = filePath.split('/');
        const dirName = pathParts[pathParts.length - 2];
        if (dirName === 'migrations') {
            return 'after_login;';
        }
        return dirName as MigrationRunType;
    };

    for (const [filePath, loader] of Object.entries(glob)) {
        const name = inferMigrationNameFromFileName(filePath);
        const runType = inferRunTypeFromFilePath(filePath);
        migrations.push({
            name, runType, migrationLoader: async () => {
                const module = await loader();
                if (!module || typeof module !== 'object' || !(module as any).migrate) {
                    throw new Error(`Migration module ${filePath} does not export a migrate() function`);
                }
                return module as MigratorModule;
            }
        });
    }
}

/**
 * Returns `true` when the server reports that the current user has data migrations to apply.
 * Only meaningful for authenticated connections; always `false` otherwise.
 */
export function hasPendingMigrations(): boolean {
    const connection = getConnection();
    if (connection.type === 'internal_authenticated') {
        return connection.migrations_to_apply !== undefined && connection.migrations_to_apply > 0;
    }
    return false;
}

export async function applyMigrations(
    runType: MigrationRunType
): Promise<void> {
    const applicableMigrations = await getResourceCollectionFromApi('migrations');

    for (const {id: name, data} of applicableMigrations) {
        const migration = migrations.find(m => m.name === name);
        if (!migration) {
            console.warn(`Migration ${name} not found, expect errors if it is actually needed!`);
            continue;
        }
        if (migration.runType !== runType) {
            continue;
        }

        const module = await migration.migrationLoader();
        if (!module.migrate) {
            console.warn(`Migration ${name} does not export a migrate() function, skipping!`);
            continue;
        }

        const ctx: MigrationContext = {
            runType,
            name,
            data
        };

        try {
            console.log(`Applying migration ${name}...`);
            await module.migrate(ctx);
        } catch (error) {
            console.error(`Error applying migration ${name}:`, error);
            alert(`An error occurred while applying migration ${name}. Please contact support.`);
            throw error;
        }

        await postToResourceAction('migrations', 'actions/apply', {migration_name: name});
    }
}

// ------------------------------------------------
// This is code to support the legacy ui
// After we migrated completely to svelte we can remove this and just run all migrations at the right times
// ------------------------------------------------

let isReadyToMigrate = false;
const waitingUntilReadyToMigrateCallbacks: Array<() => Promise<void>> = [];

/**
 * Defers `callback` until the app signals it is safe to run migrations (e.g. after the
 * legacy UI has finished its own login-time migrations). If migration is already
 * allowed when this is called, the callback fires immediately.
 *
 * Used by legacy integration code. New Svelte code should run migrations via
 * {@link OldUiBridge.runMigrations} instead.
 */
export function waitUntilReadyToMigrate(callback: () => Promise<void>): void {
    if (isReadyToMigrate) {
        callback();
        return;
    }

    waitingUntilReadyToMigrateCallbacks.push(callback);
}

export async function applyWaitingUntilReadyToMigrate(): Promise<void> {
    isReadyToMigrate = true;
    if (waitingUntilReadyToMigrateCallbacks.length === 0) {
        if (window.OLD_UI_MIGHT_NEED_MIGRATION) {
            for (let i = 0; i < 20; i++) {
                // Just wait a bit to give the old UI time to trigger any migrations it needs to after login, since we don't have a good way to know when it's done with that
                await new Promise(resolve => setTimeout(resolve, 50));
            }
        }
        return Promise.resolve();
    }

    for (const callback of waitingUntilReadyToMigrateCallbacks) {
        await callback();
    }
}
