<!--
  @component Search form above an admin table: binds the workspace's search
  term and reads from the first page on submit.
-->
<script
    lang="ts"
    generics="Row extends AdminRow, ColumnId extends string, Results extends Record<string, unknown>"
>
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import type { AdminRow } from '../schemas/admin-content.js';
    import type { AdminWorkspace } from '../workspace.svelte.js';

    const { workspace }: { workspace: AdminWorkspace<Row, ColumnId, Results> } = $props();
    const { __ } = useTranslator();
</script>

<form
    class="search"
    onsubmit={(event) => {
        event.preventDefault();
        void workspace.submitSearch();
    }}
>
    <Input
        type="search"
        aria-label={__('admin.search')}
        bind:value={workspace.search}
    />
    <Button
        type="submit"
        variant="stroke"
        disabled={workspace.loading}>{__('admin.search')}</Button
    >
</form>

<style>
    .search {
        display: flex;
        align-items: end;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
        max-width: 32rem;
    }
    .search :global(input) {
        flex: 1;
        min-width: 8rem;
    }
</style>
