<script lang="ts">
    import type { JsonApiCollection } from '../../../../resources/js/kernel/api/jsonApiEncoding.js';
    import AdminTable from '../../../../resources/js/plugins/admin/components/AdminTable.svelte';
    import {
        type AdminReader,
        useAdminRecordSet
    } from '../../../../resources/js/plugins/admin/recordSet.svelte.js';

    type TestRow = {
        id: string;
        label: string;
        active: boolean;
        kind: 'a' | 'b';
    };

    const reader: AdminReader<TestRow> = async () => [] as unknown as JsonApiCollection<TestRow>;
    const records = useAdminRecordSet(
        [{ id: 'label' }, { id: 'active' }, { id: 'controls', sortable: false }] as const,
        reader
    );

    const kind: 'a' | 'b' = records.rows[0].kind;
    // @ts-expect-error Rows keep the resource shape; nothing is renamed.
    records.rows[0].type;

    useAdminRecordSet(
        // @ts-expect-error A data column id must be a row key.
        [{ id: 'lable' }] as const,
        reader
    );
    useAdminRecordSet(
        // @ts-expect-error A display-only column must explicitly disable sorting.
        [{ id: 'controls' }] as const,
        reader
    );

    function invalidRowProperty(row: (typeof records.rows)[number]): string {
        // @ts-expect-error Cell snippets receive the reader-derived row type.
        return row.missing;
    }
</script>

{#snippet label(row: (typeof records.rows)[number])}
    <span>{row.label}</span>
{/snippet}

{#snippet controls(row: (typeof records.rows)[number])}
    <button type="button" aria-label={row.label}>Edit</button>
{/snippet}

{#snippet invalidRow(row: (typeof records.rows)[number])}
    <span>{invalidRowProperty(row)}</span>
{/snippet}

<AdminTable
    recordSet={records}
    caption="Test rows"
    cells={{ label, controls }}
/>

<AdminTable
    recordSet={records}
    caption="Invalid cell"
    cells={{
        // @ts-expect-error Cell keys must be declared column ids.
        missing: invalidRow
    }}
/>
