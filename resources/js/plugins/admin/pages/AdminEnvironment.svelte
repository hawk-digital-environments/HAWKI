<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    const { __ } = useTranslator();
</script>

<AdminWorkspace
    section="environment"
    searchable={false}
    hint={__('admin.environment_hint')}
/>
