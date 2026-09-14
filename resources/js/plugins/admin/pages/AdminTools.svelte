<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [
        { id: 'name' },
        { id: 'type', sortKey: 'kind' },
        { id: 'active', format: 'boolean' },
        { id: 'mapped_capability' }
    ];
    const workspace = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-tools', { query, signal }),
        {
            save: async (values, row) => {
                if (!row) throw new Error(__('admin.errors.save'));
                return app.restApi.updateResource('admin-tools', row.id, values, {
                    headers: { 'If-Match': `"${row._version}"` }
                });
            },
            refresh: async () => {
                for (const name of ['ai-models', 'ai-tools'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
            }
        }
    );
</script>

{#snippet mappedCapability(row: AdminRow)}
    {#if typeof row.mapped_capability === 'string' && row.mapped_capability}
        <span class="capability" data-capability={row.mapped_capability}>{row.mapped_capability}</span>
    {:else}
        —
    {/if}
{/snippet}

<AdminPage
    section="tools"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.tools')}
        {workspace}
        cells={{ mapped_capability: mappedCapability }}
    />
</AdminPage>

<style>
    .capability {
        display: inline-flex;
        padding: var(--space-1) var(--space-2_5);
        border-radius: var(--corner-xs);
        background-color: var(--capability-surface);
        color: var(--capability-color);
        font-size: var(--font-size-xs);
    }
</style>
