<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import AdminModelCapabilities from '../components/AdminModelCapabilities.svelte';
    import AdminRowSwitch from '../components/AdminRowSwitch.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    import { isModelVisible, toggleModelVisible } from '../capabilities.js';

    const { __ } = useTranslator();
</script>

{#snippet active(row: AdminRow)}
    <AdminRowSwitch
        {row}
        checked={row.active === true}
        label={__('admin.fields.active')}
        changes={(enabled) => ({ active: enabled })}
    />
{/snippet}

{#snippet visible(row: AdminRow)}
    <AdminRowSwitch
        {row}
        checked={isModelVisible(row)}
        label={__('admin.fields.visible')}
        changes={(enabled) => toggleModelVisible(row, enabled)}
    />
{/snippet}

{#snippet capabilities(row: AdminRow)}
    <AdminModelCapabilities {row} />
{/snippet}

<AdminWorkspace
    section="models"
    rowActions={[{ id: 'refresh', confirm: true }]}
    filterColumns={['provider_id']}
    cells={{ active, visible, capabilities }}
/>
