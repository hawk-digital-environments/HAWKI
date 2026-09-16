import type { AdminField, AdminRow } from '../schemas/admin-content.js';
import type { EditorSection } from './schemas.js';
import { directoryManagedFields } from '../schemas/resources/admin-users.schema.js';

/** Translation key explaining a field's editing rules when its control carries no hint of its own. */
export function fieldHint(section: EditorSection, field: AdminField, row: AdminRow | null): string | undefined {
    if (section === 'users' && field.key === 'password')
        return row ? 'admin.local_password_replace' : 'admin.local_password_hint';
    if (section === 'users' && field.key === 'password_confirmation') return undefined;
    if (section === 'users' && field.key === 'roles') return 'admin.manual_roles_hint';
    if (section === 'users' && row && !row.local_account && directoryManagedFields.includes(field.key))
        return 'admin.directory_identity_hint';
    if (field.type.startsWith('secret'))
        return row?.[field.key + '_set'] ? 'admin.secret_replace' : 'admin.secret_hint';
    if (field.immutable && row) return 'admin.immutable_hint';
    return undefined;
}
