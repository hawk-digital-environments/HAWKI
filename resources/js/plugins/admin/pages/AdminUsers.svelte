<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { roleLabel } from '../forms/authorization.js';
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
        { id: 'roles', sortable: false },
        { id: 'mapped_roles', sortable: false },
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
                        app.restApi.updateResource('admin-users', row.id, payload(values, row), {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-users', values);
            },
            refresh: () => app.refreshConnection()
        }
    );
</script>

{#snippet assignments(ids: number[], mapped: boolean)}
    {#if ids.length}
        <ul class="assignments">
            {#each ids as id (id)}
                <li>{roleLabel(id, workspace.content?.role_catalog ?? [], workspace.fields, __)}
                    <span class="source">{__(mapped ? 'admin.role_source_mapped' : 'admin.role_source_manual')}</span>
                </li>
            {/each}
        </ul>
    {:else}—{/if}
{/snippet}
{#snippet manualRoles(row: AdminUserResource)}{@render assignments(row.roles, false)}{/snippet}
{#snippet mappedRoles(row: AdminUserResource)}{@render assignments(row.mapped_roles, true)}{/snippet}

<AdminPage
    section="users"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.users')}
        cells={{ roles: manualRoles, mapped_roles: mappedRoles }}
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

<style>
    .assignments { list-style: none; padding: 0; margin: 0; }
    .assignments li + li { margin-top: var(--space-2); }
    .source { display: block; font-size: var(--font-size-xs); }
</style>
