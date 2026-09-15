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
    if (role) {
        const defaults: Record<string, string> = { admin: 'Administrator', user: 'User' };
        if (role.is_system && defaults[role.slug] === role.name) return __('admin.role_labels.' + role.slug);
        return role.name;
    }
    const option = fields
        .find((field) => ['roles', 'role_id'].includes(field.key))
        ?.options.find((item) => item.value === id);
    if (option) return option.label;
    return __('admin.role_unknown', { id: String(id) });
}
