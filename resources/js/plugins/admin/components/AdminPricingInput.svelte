<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { record, type Control } from '../forms/controls.js';
    import AdminValueInput from './AdminValueInput.svelte';
    let {
        id,
        value,
        onchange,
        disabled
    }: { id: string; value: unknown; onchange: (value: unknown) => void; disabled: boolean } = $props();
    const { __ } = useTranslator();
    const pricing = $derived(record(value));
    const price: Control = { type: 'number', min: 0, step: 'any', optional: true };
    function ranges(key: string): Record<string, unknown>[] {
        return Array.isArray(pricing[key]) ? pricing[key].map(record) : [];
    }
    function update(key: string, index: number, name: string, next: unknown) {
        onchange({
            ...pricing,
            [key]: ranges(key).map((range, i) => (i === index ? { ...range, [name]: next ?? null } : range))
        });
    }
    function bounds(range: Record<string, unknown>): [number, number | null] {
        return Array.isArray(range.range) ?
                [Number(range.range[0]), range.range[1] == null ? null : Number(range.range[1])]
            :   [0, null];
    }
</script>

<p class="hint">{__('admin.form.pricing_hint')}</p>
{#each ['ranges', 'priority_ranges'] as key}
    {@const mode =
        pricing[key] == null ? 'unknown'
        : ranges(key).length ? 'priced'
        : 'free'}
    <section aria-label={__('admin.form.labels.' + key)}>
        <SingleSelect
            triggerProps={{ 'aria-label': `${__('admin.form.labels.' + key)}: ${__('admin.form.pricing_' + mode)}` }}
            {disabled}
            value={mode}
            items={['unknown', 'free', 'priced'].map((value) => ({ value, label: __('admin.form.pricing_' + value) }))}
            onValueChange={(mode: string) =>
                onchange({
                    ...pricing,
                    [key]:
                        mode === 'unknown' ? null
                        : mode === 'free' ? []
                        : [{ currency: 'USD', range: [0, null] }]
                })}
        />
        <p class="label">{__('admin.form.labels.' + key)}</p>
        {#each ranges(key) as range, index}
            <fieldset>
                <legend>{__('admin.form.tier', { number: String(index + 1) })}</legend>
                <div class="grid">
                    <AdminValueInput
                        id={`${id}-${key}-${index}-currency`}
                        label={__('admin.form.labels.currency')}
                        control={{
                            type: 'select',
                            options: [
                                { value: 'USD', label: 'USD' },
                                { value: 'EUR', label: 'EUR' }
                            ]
                        }}
                        value={range.currency ?? 'USD'}
                        onchange={(next) => update(key, index, 'currency', next)}
                        {disabled}
                    />
                    {#each ['input_cost_per_token', 'input_cost_per_cached_token', 'output_cost_per_token', 'output_cost_per_reasoning_token'] as cost}
                        <AdminValueInput
                            id={`${id}-${key}-${index}-${cost}`}
                            label={__('admin.form.labels.' + cost)}
                            control={price}
                            value={range[cost]}
                            onchange={(next) => update(key, index, cost, next)}
                            {disabled}
                        />
                    {/each}
                    <AdminValueInput
                        id={`${id}-${key}-${index}-start`}
                        label={__('admin.form.range_start')}
                        control={{ type: 'number', min: 0, step: 1 }}
                        value={bounds(range)[0]}
                        onchange={(next) => update(key, index, 'range', [next, bounds(range)[1]])}
                        {disabled}
                    />
                    <AdminValueInput
                        id={`${id}-${key}-${index}-end`}
                        label={__('admin.form.range_end')}
                        control={{ type: 'number', min: 1, step: 1, optional: true }}
                        value={bounds(range)[1]}
                        onchange={(next) => update(key, index, 'range', [bounds(range)[0], next ?? null])}
                        {disabled}
                    />
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    {disabled}
                    aria-label={__('admin.form.remove_item', { number: String(index + 1) })}
                    onclick={() => {
                        document.getElementById(`${id}-${key}-add`)?.focus();
                        onchange({ ...pricing, [key]: ranges(key).filter((_, i) => i !== index) });
                    }}>{__('admin.form.remove')}</Button
                >
            </fieldset>
        {/each}
        {#if mode === 'priced'}<Button
                id={`${id}-${key}-add`}
                type="button"
                size="sm"
                variant="stroke"
                {disabled}
                onclick={() =>
                    onchange({
                        ...pricing,
                        [key]: [
                            ...ranges(key),
                            { currency: 'USD', range: [bounds(ranges(key).at(-1) ?? {})[1] ?? 0, null] }
                        ]
                    })}>{__('admin.form.add_tier')}</Button
            >{/if}
    </section>
{/each}

<style>
    .hint,
    .label {
        font-size: var(--font-size-sm);
        margin-block: var(--space-2);
    }
    section {
        margin-block: var(--space-4);
    }
    fieldset {
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-3);
        margin-block: var(--space-3);
        min-width: 0;
    }
    legend {
        font-size: var(--font-size-sm);
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-3);
    }
    @media (--bp-sm-and-smaller) {
        .grid {
            grid-template-columns: 1fr;
        }
    }
</style>
