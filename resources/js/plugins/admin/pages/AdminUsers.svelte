<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    const app = useApp();
</script>

<AdminWorkspace
    section="users"
    editFields={(row, fields) => fields.filter((field) =>
        Boolean(row.local_account) || !['name', 'username', 'email', 'employeetype', 'password', 'password_confirmation'].includes(field.key)
    )}
    rowActions={app.can('users.manage') ?
        [{ id: 'tokens' }, { id: 'revoke-tokens', confirm: true, destructive: true }]
    :   []}
/>
