<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import type { AdminField, AdminRow } from '../schemas/admin-content.js';

    // Built-in roles keep their slug; name, description and permissions stay editable.
    const editFields = (row: AdminRow, fields: AdminField[]) =>
        row.is_system ? fields.map((field) => (field.key === 'slug' ? { ...field, immutable: true } : field)) : fields;
</script>

<AdminWorkspace
    section="roles"
    editSystemRows
    {editFields}
/>
