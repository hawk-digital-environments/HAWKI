<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import ProviderIcon from '$plugins/core/components/ProviderIcon.svelte';
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    const app = useApp();
</script>

<AdminWorkspace
    section="providers"
    cells={{ name: providerName }}
    rowActions={[{ id: 'test' }, { id: 'discover' }]}
    pageActions={app.can('models.manage') && app.can('mcp.manage') ? [{ id: 'import', confirm: true }] : []}
/>

{#snippet providerName(row: import('../schemas/admin-content.js').AdminRow)}
    <span class="provider-name">
        <ProviderIcon
            name={String(row.name)}
            light={typeof row.icon_url === 'string' ? row.icon_url : null}
            dark={typeof row.icon_url_dark === 'string' ? row.icon_url_dark : null}
            size={24}
        />
        <span>{String(row.name)}</span>
    </span>
{/snippet}

<style>
    .provider-name {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
</style>
