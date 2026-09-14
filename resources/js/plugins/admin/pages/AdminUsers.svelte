<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { type AdminField, type AdminRow } from '../schemas/admin-content.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';
    import { UserTokensSchema, RevokeTokensSchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [
        { id: 'name' },
        { id: 'username' },
        { id: 'email' },
        { id: 'employeetype' },
        { id: 'admin_disabled', format: 'boolean' },
        { id: 'last_login_at' }
    ];
    // Directory accounts are managed by the identity provider; only local accounts expose those fields.
    const editFields = (row: AdminRow, fields: AdminField[]) =>
        fields.filter(
            (field) =>
                Boolean(row.local_account) ||
                !['name', 'username', 'email', 'employeetype', 'password', 'password_confirmation'].includes(field.key)
        );
    const workspace = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-users', { query, signal }),
        {
            rowActions: (row) => ({
                'tokens':
                    app.can('users.manage') ?
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
                    app.can('users.manage') ?
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
                        app.restApi.updateResource('admin-users', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-users', values);
            },
            refresh: () => app.refreshConnection()
        }
    );
</script>

<AdminPage
    section="users"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.users')}
        {workspace}
    />
    <AdminResultDialog
        {workspace}
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
