/**
 * The Admin Panel's own Sections and Workspaces, as plain data. `AdminModule`
 * declares them through the same registrar every other Module uses (see
 * `api.ts`), so the panel never treats its built-in Workspaces differently
 * from added ones. Kept free of component imports so tests can load it.
 */
export const builtInSections = ['ai', 'people', 'system'] as const;

export type BuiltInSectionId = (typeof builtInSections)[number];

export const builtInWorkspaces = [
    { id: 'providers', section: 'ai' },
    { id: 'models', section: 'ai' },
    { id: 'system-models', section: 'ai' },
    { id: 'mcp', section: 'ai' },
    { id: 'users', section: 'people' },
    { id: 'announcements', section: 'system' },
    { id: 'usage', section: 'system' },
    { id: 'health', section: 'system' },
    { id: 'settings', section: 'system' },
    { id: 'environment', section: 'system' }
] as const satisfies ReadonlyArray<{ id: string; section: BuiltInSectionId }>;

/** Ids of the built-in Workspaces; also the keys of their editor schemas and `admin.sections.<id>` labels. */
export type WorkspaceId = (typeof builtInWorkspaces)[number]['id'];
