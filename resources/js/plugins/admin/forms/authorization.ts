import type { PermissionCatalogEntry, RoleCatalogEntry, AdminField } from '../schemas/admin-content.js';

type Translate = (label: string, replacements?: Record<string, string>) => string;

/** Keep grants missing from the current registry visible and unchanged. */
export function permissionChoices(catalog: PermissionCatalogEntry[], selected: string[]): PermissionCatalogEntry[] {
    return [
        ...catalog,
        ...selected
            .filter((name) => !catalog.some((entry) => entry.name === name))
            .map((name) => ({
                name,
                group: 'administration' as const,
                title_label: '',
                description_label: '',
                grantable: false
            }))
    ];
}

/** Groups present in the catalog, `administration` first and the rest in first-seen order. */
export function permissionGroups(entries: PermissionCatalogEntry[]): string[] {
    const groups = [...new Set(entries.map((entry) => entry.group))];
    return groups.includes('administration') ?
            ['administration', ...groups.filter((group) => group !== 'administration')]
        :   groups;
}

export function changePermission(
    catalog: PermissionCatalogEntry[],
    selected: string[],
    name: string,
    checked: boolean
): string[] {
    if (!catalog.find((entry) => entry.name === name)?.grantable) return selected;
    return checked ? [...new Set([...selected, name])] : selected.filter((value) => value !== name);
}

export function roleLabel(id: number, catalog: RoleCatalogEntry[], fields: AdminField[], __: Translate): string {
    const role = catalog.find((entry) => entry.id === id);
    // The backend sets `title_label` for system roles; custom roles carry their stored name.
    if (role) return role.title_label ? __(role.title_label) : role.name;
    const option = fields
        .find((field) => ['roles', 'role_id', 'allowed_roles'].includes(field.key))
        ?.options.find((item) => item.value === id);
    if (option) return option.label;
    return __('admin.role_unknown', { id: String(id) });
}
