<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { roleLabel } from '../forms/authorization.js';
    import type { AdminRoleMappingResource } from '../schemas/resources/admin-mappings.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminRoleMappingResource>[] = [{ id: 'employee_type' }, { id: 'role_id' }];
    const records = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-mappings', { query, signal }),
        {
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-mappings', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-mappings', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-mappings', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: () => app.refreshConnection()
        }
    );
</script>

{#snippet mappedRole(row: AdminRoleMappingResource)}
    {roleLabel(row.role_id, records.content?.role_catalog ?? [], records.fields, __)}
{/snippet}

<AdminPage
    workspace="mappings"
    hint={__('admin.mapping_impact')}
    recordSet={records}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.mappings')}
        cells={{ role_id: mappedRole }}
        recordSet={records}
    />
</AdminPage>
