<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminEnvironmentResource } from '../schemas/resources/admin-environment.schema.js';
    import { type AdminColumn, useAdminRecordSet } from '../recordSet.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminEnvironmentResource>[] = [{ id: 'key' }, { id: 'value' }, { id: 'source', format: 'enum' }];
    const records = useAdminRecordSet(columns, (signal, query) =>
        app.restApi.getResourceCollection('admin-environment', { query, signal })
    );
</script>

<AdminPage
    workspace="environment"
    recordSet={records}
    hint={__('admin.environment_hint')}
>
    <AdminTable
        caption={__('admin.sections.environment')}
        recordSet={records}
    />
</AdminPage>
