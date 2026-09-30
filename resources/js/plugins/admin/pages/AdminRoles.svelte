<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminField } from '../schemas/admin-content.js';
    import type { AdminRoleResource } from '../schemas/resources/admin-roles.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminRoleResource>[] = [
        { id: 'name' },
        { id: 'slug' },
        { id: 'description' },
        { id: 'is_system', format: 'boolean' }
    ];
    // Built-in roles keep their slug; name, description and permissions stay editable.
    const editFields = (row: AdminRoleResource, fields: AdminField[]) =>
        row.is_system ? fields.map((field) => (field.key === 'slug' ? { ...field, immutable: true } : field)) : fields;
    const records = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-roles', { query, signal }),
        {
            editFields,
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-roles', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-roles', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-roles', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: () => app.refreshConnection()
        }
    );
</script>

<AdminPage
    workspace="roles"
    recordSet={records}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.roles')}
        recordSet={records}
        editSystemRows
    />
</AdminPage>
