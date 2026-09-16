<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminAnnouncementResource } from '../schemas/resources/admin-announcements.schema.js';
    import { type AdminColumn, useAdminRecordSet } from '../recordSet.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminAnnouncementResource>[] = [
        { id: 'title' },
        { id: 'kind' },
        { id: 'is_published', format: 'boolean' },
        { id: 'starts_at' },
        { id: 'expires_at' },
        { id: 'seen_count', sortable: false },
        { id: 'accepted_count', sortable: false }
    ];
    const records = useAdminRecordSet(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-announcements', { query, signal }),
        {
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-announcements', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-announcements', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-announcements', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: async () => {
                for (const name of ['announcements'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
            }
        }
    );
</script>

<AdminPage
    workspace="announcements"
    recordSet={records}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.announcements')}
        recordSet={records}
    />
</AdminPage>
