<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [{ id: 'employee_type' }, { id: 'role_id' }];
    const workspace = useAdminWorkspace(
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

<AdminPage
    section="mappings"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.mappings')}
        {workspace}
    />
</AdminPage>
