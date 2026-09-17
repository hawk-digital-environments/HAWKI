export const sections = [
    { id: 'providers', permission: 'providers.manage', group: 'ai' },
    { id: 'models', permission: 'models.manage', group: 'ai' },
    { id: 'system-models', permission: 'models.manage', group: 'ai' },
    { id: 'mcp', permission: 'mcp.manage', group: 'ai' },
    { id: 'tools', permission: 'mcp.manage', group: 'ai' },
    { id: 'assistants', permission: 'assistants.manage', group: 'assistants' },
    { id: 'users', permission: 'users.view', group: 'people' },
    { id: 'roles', permission: 'roles.manage', group: 'people' },
    { id: 'mappings', permission: 'roles.manage', group: 'people' },
    { id: 'announcements', permission: 'announcements.manage', group: 'system' },
    { id: 'usage', permission: 'usage.view', group: 'system' },
    { id: 'health', permission: 'health.view', group: 'system' },
    { id: 'settings', permission: 'settings.manage', group: 'system' },
    { id: 'environment', permission: 'settings.view', group: 'system' }
] as const;

export type SectionId = (typeof sections)[number]['id'];
