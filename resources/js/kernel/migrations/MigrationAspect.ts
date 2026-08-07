import type {JsonApiCollection} from '$lib/kernel/api/jsonApiEncoding.js';
import type {HawkiApp, HawkiAppAspect, UnfinishedHawkiApp, WithoutAppAspectInternals} from '$lib/kernel/HawkiApp.js';
import type {Migration} from '$lib/app/schemas/resources/migrations.schema.js';
import {createMigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppAspects {
        readonly migration: WithoutAppAspectInternals<MigrationAspect>;
    }
}

export type MigrationRunType = string | ('after_login' | 'after_passkey');

export interface MigrationContext {
    runType: MigrationRunType;
    name: string;
    app: HawkiApp;
    data?: any;
}

export type Migrator = (ctx: MigrationContext) => Promise<void>;

export interface MigrationDefinition {
    name: string;
    runType: MigrationRunType;
    migrationLoader: () => Promise<Migrator>;
}

export class MigrationAspect implements HawkiAppAspect {
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
            return this.app.authenticatedConnection.migrations_to_apply || 0;
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
