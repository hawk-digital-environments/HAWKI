import type {JsonApiCollection} from '$lib/kernel/api/jsonApiEncoding.js';
import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {Migration} from '$lib/app/schemas/resources/migrations.schema.js';
import {createMigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.migration` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly migration: WithoutAppExtensionInternals<MigrationExtension>;
    }
}

export type MigrationRunType = string | ('after_login' | 'after_passkey');

/**
 * Argument passed to every {@link Migrator}. The migration name and run type
 * come from the server's `migrations` resource (see {@link MigrationExtension.apply});
 * `app` is the fully-built {@link HawkiApp} so migrators can reach stores, the
 * keychain, the REST API, etc. `data` is the optional server-provided payload
 * from the migration's JSON:API record, if the migration needs extra context
 * the server computed.
 */
export interface MigrationContext {
    runType: MigrationRunType;
    name: string;
    app: HawkiApp;
    data?: any;
}

/**
 * A single migration's body. Receives a {@link MigrationContext} and performs
 * whatever client-side data transformation it owns (e.g. upgrading the stored
 * keychain format after a passkey change). The contract is: the migrator either
 * resolves on success or throws — `MigrationExtension.apply` surfaces the error
 * to the user and aborts the run, so a failed migration is never silently
 * skipped or marked as applied.
 */
export type Migrator = (ctx: MigrationContext) => Promise<void>;

/**
 * Registration record for a migration. `migrationLoader` is a lazy loader (a
 * `() => Promise<Migrator>`) so the migration body is only imported when the
 * server actually asks for it — keeping one-shot migration code out of the main
 * bundle until it's needed.
 */
export interface MigrationDefinition {
    name: string;
    runType: MigrationRunType;
    migrationLoader: () => Promise<Migrator>;
}

/**
 * App extension that owns the registry of client-side migrations and drives
 * their application against the server's pending-migration list.
 *
 * HAWKI migrations are paired client/server records: the server tracks which
 * migrations a user still needs (`migrations_to_apply` on the connection) and
 * lists them via the `migrations` JSON:API resource; the client owns the matching
 * migrator code (registered through the {@link MigrationRegistrar}). The
 * extension pulls the pending list, finds each migration by name, and runs it
 * in order, marking each applied on the server before moving on. Migrations are
 * partitioned by {@link MigrationRunType} (`'after_login'` / `'after_passkey'`)
 * so the caller can run only the slice that matches the current trigger.
 *
 * Plugins register migrations during the bootstrapper's `migrations` stage
 * (driven by `PluginBootstrapper`); the `core` plugin lazy-globs
 * the `plugins/core/migrations/` directory, inferring name and run type from the
 * file path (see `migrationRegistrar.ts`).
 */
export class MigrationExtension implements HawkiAppExtension {
    private appliedMigrationCount = 0;
    private migrations = new Map<string, MigrationDefinition>();
    private _app: HawkiApp | null = null;

    public get hasPending(): boolean {
        return (this.getNumberOfNotAppliedMigrations() - this.appliedMigrationCount) > 0;
    }

    public async apply(runType: MigrationRunType): Promise<void> {
        if (!this.hasPending) {
            console.warn('applyMigrations called but no pending migrations reported by server, skipping!');
            return;
        }

        const applicableMigrations = await this.getApplicableMigrations();

        for (const {id: name, data} of applicableMigrations) {
            const migration = this.migrations.get(name);
            if (!migration) {
                console.warn(`Migration ${name} not found, expect errors if it is actually needed!`);
                continue;
            }
            if (migration.runType !== runType) {
                continue;
            }

            const ctx: MigrationContext = {
                app: this.app,
                runType,
                name,
                data
            };

            try {
                console.log(`Applying migration ${name}...`);
                await (await migration.migrationLoader())(ctx);
            } catch (error) {
                console.error(`Error applying migration ${name}:`, error);
                alert(`An error occurred while applying migration ${name}. Please contact support.`);
                throw error;
            }

            await this.markMigrationApplied(name);
            this.appliedMigrationCount++;
        }
    }

    private get app(): HawkiApp {
        if (!this._app) {
            throw new Error('App is not initialized yet');
        }
        return this._app;
    }

    private getNumberOfNotAppliedMigrations(): number {
        try {
            const connection = this.app.connection;
            return connection.isAuthenticated ? connection.migrations_to_apply || 0 : 0;
        } catch (e) {
            return 0;
        }
    }

    private getApplicableMigrations(): Promise<JsonApiCollection<Migration>> {
        return this.app.restApi.getResourceCollection('migrations');
    }

    private markMigrationApplied(migrationName: string): Promise<void> {
        return this.app.restApi.postToResourceAction(
            'migrations',
            'actions/apply',
            {migration_name: migrationName});
    }

    public async init(app: UnfinishedHawkiApp) {
        const registrar = createMigrationRegistrar(this.migrations);
        await app.getOrFail('plugins').bootstrapper.runMigrations(registrar);
    }

    public ready(app: HawkiApp): void {
        this._app = app;
    }

    public provideProperties(): Record<string, any> {
        return {
            migration: this
        };
    }
}
