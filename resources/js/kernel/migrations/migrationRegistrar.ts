import type {MigrationDefinition, MigrationRunType, Migrator} from '$lib/kernel/migrations/MigrationExtension.js';
import {globModuleLoader} from '$lib/utils/globModuleLoader.js';

/**
 * Registrar builder for the {@link MigrationExtension}.
 *
 * Migrations are registered under their file-derived name and run-type. The
 * name is the filename stem (e.g. `2026_06_10_190815_after_passkey_upgrade_room_key_format.ts`
 * → that whole stem); the run type is inferred from the **parent directory**
 * name — files directly under `migrations/` default to `'after_login'`, files
 * under a subdirectory like `migrations/after_passkey/` get that directory name
 * as their run type. `add` throws on duplicate names so accidental collisions
 * surface loudly. `addFromModules` is the glob-powered entry: it lazy-loads a
 * `Record<path, () => Promise<unknown>>` (the `import.meta.glob` non-eager form),
 * validates each module exports a function under the `migrate` key, and
 * registers each as a {@link MigrationDefinition} with its loader.
 */
export function createMigrationRegistrar(
    migrations: Map<string, MigrationDefinition>
) {
    function add(name: string, runType: MigrationRunType, migrationLoader: () => Promise<Migrator>): void {
        if (migrations.has(name)) {
            throw new Error(`Migration with name ${name} is already registered`);
        }

        migrations.set(name, {name, runType, migrationLoader});
    }

    function addFromModules(modules: Record<string, () => Promise<unknown>>): void {
        function inferMigrationNameFromFileName(filePath: string): string {
            const match = filePath.match(/\/([^\/]+)\.ts$/);
            if (!match) {
                throw new Error(`Could not infer migration name from file path ${filePath}`);
            }
            return match[1];
        }

        function inferRunTypeFromFilePath(filePath: string): MigrationRunType {
            const pathParts = filePath.split('/');
            const dirName = pathParts[pathParts.length - 2];
            if (dirName === 'migrations') {
                return 'after_login';
            }
            return dirName as MigrationRunType;
        }

        const loadedMigrations = globModuleLoader<() => Promise<unknown>, () => Promise<Migrator>>(
            modules,
            {
                valueKey: 'migrate',
                validate: v => typeof v === 'function'
            }
        );

        for (const [filePath, migrationLoader] of Object.entries(loadedMigrations)) {
            const name = inferMigrationNameFromFileName(filePath);
            const runType = inferRunTypeFromFilePath(filePath);
            add(name, runType, migrationLoader);
        }
    }

    return {
        add,
        addFromModules
    };
}

export type MigrationRegistrar = ReturnType<typeof createMigrationRegistrar>;
