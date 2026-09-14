<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
</script>

<AdminWorkspace
    section="mcp"
    rowActions={[{ id: 'test' }, { id: 'discover', confirm: true }]}
/>
