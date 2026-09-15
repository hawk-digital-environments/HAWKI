<script lang="ts">
    import type { JsonApiCollection } from '../../../../resources/js/kernel/api/jsonApiEncoding.js';
    import AdminTable from '../../../../resources/js/plugins/admin/components/AdminTable.svelte';
    import {
        type AdminReader,
        useAdminWorkspace
    } from '../../../../resources/js/plugins/admin/workspace.svelte.js';

    type TestRow = {
        id: string;
        label: string;
        active: boolean;
        kind: 'a' | 'b';
    };

    const reader: AdminReader<TestRow> = async () => [] as unknown as JsonApiCollection<TestRow>;
    const workspace = useAdminWorkspace(
        [{ id: 'label' }, { id: 'active' }, { id: 'controls', sortable: false }] as const,
        reader
    );

    const kind: 'a' | 'b' = workspace.rows[0].kind;
    // @ts-expect-error Rows keep the resource shape; nothing is renamed.
    workspace.rows[0].type;

    useAdminWorkspace(
        // @ts-expect-error A data column id must be a row key.
        [{ id: 'lable' }] as const,
        reader
    );
    useAdminWorkspace(
        // @ts-expect-error A display-only column must explicitly disable sorting.
        [{ id: 'controls' }] as const,
        reader
    );

    function invalidRowProperty(row: (typeof workspace.rows)[number]): string {
        // @ts-expect-error Cell snippets receive the reader-derived row type.
        return row.missing;
    }
</script>

{#snippet label(row: (typeof workspace.rows)[number])}
    <span>{row.label}</span>
{/snippet}

{#snippet controls(row: (typeof workspace.rows)[number])}
    <button type="button" aria-label={row.label}>Edit</button>
{/snippet}

{#snippet invalidRow(row: (typeof workspace.rows)[number])}
    <span>{invalidRowProperty(row)}</span>
{/snippet}

<AdminTable
    {workspace}
    caption="Test rows"
    cells={{ label, controls }}
/>

<AdminTable
    {workspace}
    caption="Invalid cell"
    cells={{
        // @ts-expect-error Cell keys must be declared column ids.
        missing: invalidRow
    }}
/>
