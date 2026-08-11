import type {MigrationRunType} from '$lib/kernel/migrations/MigrationExtension.js';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * Returns `true` when the server reports that the current user has data migrations to apply.
 * Only meaningful for authenticated connections; always `false` otherwise.
 * @deprecated Use `useApp().migrations.hasPending` instead. This function is a legacy wrapper and will be removed in future versions.
 */
export function hasPendingMigrations(): boolean {
    return getHawkiApp().migration.hasPending;
}

/**
 * Applies all pending migrations for the current user, if any. This is typically called after login or after a passkey registration.
 * @param runType
 * @deprecated Use `useApp().migrations.apply(runType)` instead. This function is a legacy wrapper and will be removed in future versions.
 */
export async function applyMigrations(runType: MigrationRunType): Promise<void> {
    return getHawkiApp().migration.apply(runType);
}
