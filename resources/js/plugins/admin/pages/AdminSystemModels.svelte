<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminSystemModelResource } from '../schemas/resources/admin-system-models.schema.js';
    import { type AdminColumn, useAdminRecordSet } from '../recordSet.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminSystemModelResource>[] = [{ id: 'model_type' }, { id: 'usage_type' }, { id: 'model_id' }];
    const records = useAdminRecordSet(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-system-models', { query, signal }),
        {
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-system-models', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-system-models', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-system-models', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: async () => {
                for (const name of ['ai-models', 'ai-tools', 'system-prompts'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
            }
        }
    );
</script>

<AdminPage
    workspace="system-models"
    recordSet={records}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.system-models')}
        recordSet={records}
    />
</AdminPage>
