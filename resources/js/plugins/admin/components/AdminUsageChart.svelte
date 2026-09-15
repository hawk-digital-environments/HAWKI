<script lang="ts">
    import { barX, defineChart } from '@tanstack/charts';
    import { scaleBand } from '@tanstack/charts/scales/band';
    import { scaleLinear } from '@tanstack/charts/scales/linear';
    import { Chart } from '@tanstack/charts/svelte';
    import { tooltip } from '@tanstack/charts/tooltip';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminUsageResource } from '../schemas/resources/admin-usage.schema.js';

    let { rows, group }: { rows: AdminUsageResource[]; group: string } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const locale = $derived(app.localization.locale.lang.replace('_', '-'));
    const numbers = $derived(new Intl.NumberFormat(locale));
    const compactNumbers = $derived(new Intl.NumberFormat(locale, { notation: 'compact' }));
    const data = $derived(rows.slice(0, 31).map((row) => ({
        id: row.id,
        label: row.label,
        prompt: row.prompt_tokens,
        completion: row.completion_tokens,
        total: row.prompt_tokens + row.completion_tokens
    })));
    const definition = $derived(defineChart({
        marks: [barX(data, {
            id: 'usage-tokens',
            x: 'total',
            y: 'id',
            fill: 'var(--color-accent-text)',
            radius: { end: 3 },
            maxThickness: 20
        })],
        scales: {
            x: {
                scale: scaleLinear().domain([0, Math.max(1, ...data.map((row) => row.total))]),
                nice: 5,
                grid: true,
                axis: {
                    label: { text: __('admin.total_tokens'), opacity: 1, fontSize: 12 },
                    tickLabels: { opacity: 1, fontSize: 12 },
                    ticks: { format: (value) => compactNumbers.format(value) }
                }
            },
            y: {
                scale: scaleBand<string>().domain(data.map((row) => row.id)).padding(0.3),
                axis: {
                    tickLabels: { opacity: 1, fontSize: 12 },
                    ticks: {
                        format: (value) => {
                            const label = data.find((row) => row.id === value)?.label ?? value;
                            return label.length > 18 ? `${label.slice(0, 17)}…` : label;
                        }
                    }
                }
            }
        },
        theme: {
            foreground: 'var(--color-text)',
            muted: 'var(--color-text-muted)',
            grid: 'var(--color-border)',
            background: 'var(--color-surface)'
        },
        tooltip: {
            use: tooltip,
            items: [
                { field: 'label', label: __('admin.grouping.' + group) },
                { field: 'total', label: __('admin.total_tokens'), text: (point) => numbers.format(point.datum.total) },
                { field: 'prompt', label: __('admin.fields.prompt_tokens'), text: (point) => numbers.format(point.datum.prompt) },
                { field: 'completion', label: __('admin.fields.completion_tokens'), text: (point) => numbers.format(point.datum.completion) }
            ]
        }
    }));
</script>

<figure>
    <figcaption>{__('admin.token_chart')}</figcaption>
    <Chart
        {definition}
        ariaLabel={__('admin.token_chart')}
        ariaDescription={__('admin.chart_keyboard_hint')}
        height={Math.max(180, data.length * 28 + 64)}
    />
</figure>

<style>
    figure {
        min-width: 0;
        margin: var(--space-6) 0;
        --ts-chart-tooltip-background: var(--color-surface);
        --ts-chart-tooltip-color: var(--color-text);
        --ts-chart-tooltip-border: 1px solid var(--color-border-strong);
        --ts-chart-tooltip-font: inherit;
    }
    figcaption {
        font-weight: 600;
        margin-bottom: var(--space-3);
    }
</style>
