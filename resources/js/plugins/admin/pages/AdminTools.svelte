<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminToolResource } from '../schemas/resources/admin-tools.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminToolResource>[] = [
        { id: 'name' },
        { id: 'kind' },
        { id: 'active', format: 'boolean' },
        { id: 'access_rule', sortable: false },
        { id: 'mapped_capability' }
    ];
    const workspace = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-tools', { query, signal }),
        {
            editFields: (row, fields) => fields.filter((field) => field.key !== 'access_rule' || (app.can('mcp.manage') && app.can('roles.manage'))),
            save: async (values, row) => {
                if (!row) throw new Error(__('admin.errors.save'));
                return app.restApi.updateResource('admin-tools', row.id, values, {
                    headers: { 'If-Match': `"${row._version}"` }
                });
            },
            refresh: () => app.refreshConnection()
        }
    );
</script>

{#snippet mappedCapability(row: AdminToolResource)}
    {#if row.mapped_capability}
        <span class="capability" data-capability={row.mapped_capability}>{row.mapped_capability}</span>
    {:else}
        —
    {/if}
{/snippet}

{#snippet accessRule(row: AdminToolResource)}
    {@const rule = workspace.content?.access_rules.find((entry) => entry.name === row.access_rule)}
    {__(rule?.title_label ?? 'admin.tool_access_rules.unavailable.title')}
{/snippet}

<AdminPage
    section="tools"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.tools')}
        {workspace}
        cells={{ mapped_capability: mappedCapability, access_rule: accessRule }}
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
