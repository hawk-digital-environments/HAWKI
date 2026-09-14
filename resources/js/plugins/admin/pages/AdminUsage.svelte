<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import AdminUsageChart from '../components/AdminUsageChart.svelte';
    import type { AdminContent } from '../schemas/admin-content.js';
    import { adminActionIcons } from '../actionIcons.js';
    const app = useApp();
    const { __ } = useTranslator();
    const uid = $props.id();
    const initial = {
        from: new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10),
        to: new Date().toISOString().slice(0, 10),
        group_by: 'day'
    };
    let from = $state(initial.from);
    let to = $state(initial.to);
    let group = $state(initial.group_by);
    let applied = $state(initial);
    function exportCsv(content: AdminContent | null) {
        const keys = content?.columns ?? [];
        const encode = (value: unknown) =>
            '"' +
            String(value ?? '')
                .replace(/^[=+@-]/, "'$&")
                .replaceAll('"', '""') +
            '"';
        const csv = [
            keys.map((key) => __('admin.fields.' + key)),
            ...(content?.rows ?? []).map((row) => keys.map((key) => row[key]))
        ]
            .map((row) => row.map(encode).join(','))
            .join('\r\n');
        const url = URL.createObjectURL(new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `hawki-usage-${applied.from}-${applied.to}.csv`;
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
</script>

<AdminWorkspace
    section="usage"
    searchable={false}
    query={() => applied}
    hint={applied.group_by === 'user' ? __('admin.usage_privacy') : undefined}
    menuItems={({ content, loading }) => [
        { label: __('admin.export'), icon: adminActionIcons.export, disabled: !content || loading, run: () => exportCsv(content) }
    ]}
>
    {#snippet filters({ loading, reload })}
        <form
            onsubmit={(event) => {
                event.preventDefault();
                applied = { from, to, group_by: group };
                void reload();
            }}
        >
            <label for={`${uid}-from`}
                >{__('admin.from')}<Input
                    id={`${uid}-from`}
                    type="date"
                    bind:value={from}
                    required
                /></label
            >
            <label for={`${uid}-to`}
                >{__('admin.to')}<Input
                    id={`${uid}-to`}
                    type="date"
                    bind:value={to}
                    min={from}
                    required
                /></label
            >
            <div class="group-filter">
                <span id={`${uid}-group-label`}>{__('admin.group_by')}</span><SingleSelect
                    bind:value={group}
                    triggerProps={{ 'aria-labelledby': `${uid}-group-label` }}
                    items={[
                        'day',
                        'month',
                        'model',
                        'provider',
                        'type',
                        ...(app.can('usage.view-per-user') ? ['user'] : [])
                    ].map((value) => ({ value, label: __('admin.grouping.' + value) }))}
                />
            </div>
            <Button
                type="submit"
                variant="stroke"
                disabled={loading}>{__('admin.apply')}</Button
            >
        </form>
    {/snippet}
    {#snippet beforeTable({ content })}
        {#if content?.totals}
            <dl class="statistics">
                {#each Object.entries(content.totals) as [key, value]}<div>
                        <dt>{__('admin.fields.' + key)}</dt>
                        <dd>{Number(value).toLocaleString(app.localization.locale.lang.replace('_', '-'))}</dd>
                    </div>{/each}
            </dl>
            {#if content.rows.length}
                <AdminUsageChart rows={content.rows} group={applied.group_by} />
            {/if}
        {/if}
    {/snippet}
</AdminWorkspace>

<style>
    form {
        display: flex;
        align-items: end;
        flex-wrap: wrap;
        gap: var(--space-3);
    }
    label,
    .group-filter {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        font-size: var(--font-size-sm);
    }
    .statistics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-4);
        margin-block: var(--space-6);
    }
    .statistics div {
        border-left: 3px solid var(--color-accent-text);
        padding: var(--space-2) var(--space-4);
    }
    dt {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
    dd {
        margin: var(--space-1) 0 0;
        font-variant-numeric: tabular-nums;
    }
    .statistics dd {
        font-size: 2rem;
        font-weight: 600;
    }
    @media (--bp-md-and-smaller) {
        .statistics {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
