<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminField } from '../schemas/admin-content.js';
    import { type AdminUserResource, directoryManagedFields } from '../schemas/resources/admin-users.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    import { UserTokensSchema, RevokeTokensSchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminUserResource>[] = [
        { id: 'name' },
        { id: 'username' },
        { id: 'email' },
        { id: 'employeetype' },
        { id: 'admin_disabled', format: 'boolean' },
        { id: 'last_login_at' }
    ];
    // The identity provider owns directory accounts: their profile fields stay
    // visible but read-only, and a sign-in password cannot be set for them.
    const editFields = (row: AdminUserResource, fields: AdminField[]) =>
        row.local_account ? fields
        :   fields
                .filter((field) => !['password', 'password_confirmation'].includes(field.key))
                .map((field) => (directoryManagedFields.includes(field.key) ? { ...field, immutable: true } : field));
    /** The backend rejects profile fields for directory accounts even when unchanged. */
    const payload = (values: Record<string, unknown>, row: AdminUserResource | null) =>
        row && !row.local_account ?
            Object.fromEntries(Object.entries(values).filter(([key]) => !directoryManagedFields.includes(key)))
        :   values;
    const records = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-users', { query, signal }),
        {
            rowActions: (row) => ({
                'tokens':
                    app.isAdmin ?
                        {
                            dialog: true,
                            run: () =>
                                app.restApi.getFromResourceAction(
                                    'admin-users',
                                    `${encodeURIComponent(row.id)}/actions/tokens`,
                                    { schema: UserTokensSchema }
                                )
                        }
                    :   undefined,
                'revoke-tokens':
                    app.isAdmin ?
                        {
                            confirm: true,
                            destructive: true,
                            run: () =>
                                app.restApi.postToResourceAction(
                                    'admin-users',
                                    `${encodeURIComponent(row.id)}/actions/revoke-tokens`,
                                    {},
                                    { schema: RevokeTokensSchema }
                                )
                        }
                    :   undefined
            }),
            editFields,
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-users', row.id, payload(values, row), {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-users', values);
            },
            refresh: () => app.refreshConnection()
        }
    );
</script>

<AdminPage
    workspace="users"
    recordSet={records}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.users')}
        recordSet={records}
    />
    <AdminResultDialog
        recordSet={records}
        action="tokens"
    >
        {#snippet children(response)}
            <ul>
                {#each response.tokens as token}
                    <li>{token.name} · {token.last_used_at ?? __('admin.never')}</li>
                {/each}
            </ul>
        {/snippet}
    </AdminResultDialog>
</AdminPage>
