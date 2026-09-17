/**
 * The Admin Panel's own Sections and Workspaces, as plain data. `AdminModule`
 * declares them through the same registrar every other Module uses (see
 * `api.ts`), so the panel never treats its built-in Workspaces differently
 * from added ones. Kept free of component imports so tests can load it.
 */
export const builtInSections = ['ai', 'people', 'system'] as const;

export type BuiltInSectionId = (typeof builtInSections)[number];

export const builtInWorkspaces = [
    { id: 'providers', permission: 'providers.manage', section: 'ai' },
    { id: 'models', permission: 'models.manage', section: 'ai' },
    { id: 'system-models', permission: 'models.manage', section: 'ai' },
    { id: 'mcp', permission: 'mcp.manage', section: 'ai' },
    { id: 'users', permission: 'users.view', section: 'people' },
    { id: 'roles', permission: 'roles.manage', section: 'people' },
    { id: 'mappings', permission: 'roles.manage', section: 'people' },
    { id: 'announcements', permission: 'announcements.manage', section: 'system' },
    { id: 'usage', permission: 'usage.view', section: 'system' },
    { id: 'health', permission: 'health.view', section: 'system' },
    { id: 'settings', permission: 'settings.manage', section: 'system' },
    { id: 'environment', permission: 'settings.view', section: 'system' }
] as const satisfies ReadonlyArray<{ id: string; permission: string; section: BuiltInSectionId }>;

/** Ids of the built-in Workspaces; also the keys of their editor schemas and `admin.sections.<id>` labels. */
export type WorkspaceId = (typeof builtInWorkspaces)[number]['id'];
