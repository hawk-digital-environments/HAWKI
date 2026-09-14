<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { AdminFieldSchema } from '../schemas/admin-content.js';
    import { settingsTabs } from '../settings.js';

    const { __ } = useTranslator();
    const uid = $props.id();
    let activeTab = $state('system');
    const tabs = $derived(settingsTabs.map(tab => ({
        key: tab.key,
        label: __('admin.settings_tabs.' + tab.key),
        id: `${uid}-tab-${tab.key}`,
        panelId: `${uid}-panel-${tab.key}`
    })));

    function display(value: unknown): string {
        if (value === null || value === undefined || value === '') return __('admin.settings_empty');
        if (typeof value === 'boolean') return __(value ? 'admin.yes' : 'admin.no');
        if (Array.isArray(value)) return value.length ? value.join(', ') : __('admin.settings_empty');
        return String(value);
    }
</script>

<AdminWorkspace
    section="settings"
    searchable={false}
    editable
    resettable
    hint={__('admin.settings_hint')}
    editFields={(row) => [AdminFieldSchema.parse({
        key: 'value',
        type: Array.isArray(row.options) && row.options.length ? 'select' : row.type === 'string' ? 'text' : String(row.type),
        options: row.options ?? [],
        required: row.type !== 'string'
    })]}
>
    {#snippet body(context)}
        <div class="settings-tabs">
            <Tabs items={tabs} bind:value={activeTab} aria-label={__('admin.sections.settings')} />
        </div>
        {#each settingsTabs as tab (tab.key)}
            <div
                id={`${uid}-panel-${tab.key}`}
                role="tabpanel"
                aria-labelledby={`${uid}-tab-${tab.key}`}
                aria-busy={context.loading}
                tabindex="0"
                hidden={activeTab !== tab.key}
            >
                {#each tab.groups as group (group.key)}
                    <section class="settings-group" aria-labelledby={`${uid}-group-${group.key}`}>
                        <div class="group-description">
                            <h2 id={`${uid}-group-${group.key}`}>{__('admin.settings_groups.' + group.key + '.title')}</h2>
                            <p>{__('admin.settings_groups.' + group.key + '.description')}</p>
                        </div>
                        <ul class="settings-card">
                            {#each group.settings as key (key)}
                                {@const row = context.content?.rows.find(row => row.key === key)}
                                {#if row}
                                    <li>
                                        <div class="setting-label">
                                            <h3>{__('admin.settings_labels.' + key)}</h3>
                                            <small>{__('admin.values.' + row.source)}</small>
                                            {#if row.source === 'database'}
                                                <small>{__('admin.fields.default')}: {display(row.default)}</small>
                                            {/if}
                                        </div>
                                        <p class="setting-value">{display(row.value)}</p>
                                        <div class="setting-actions">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={context.loading || context.busy}
                                                aria-label={__('admin.settings_edit', {name: __('admin.settings_labels.' + key)})}
                                                onclick={(event) => context.edit(row, event.currentTarget)}
                                            >{__('admin.edit')}</Button>
                                            {#if row.source === 'database'}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={context.loading || context.busy}
                                                    aria-label={__('admin.settings_reset', {name: __('admin.settings_labels.' + key)})}
                                                    onclick={(event) => context.reset(row, event.currentTarget.parentElement?.querySelector('button') ?? event.currentTarget)}
                                                >{__('admin.reset')}</Button>
                                            {/if}
                                        </div>
                                    </li>
                                {/if}
                            {/each}
                        </ul>
                    </section>
                {/each}
            </div>
        {/each}
    {/snippet}
</AdminWorkspace>

<style>
    .settings-tabs {
        max-width: 48rem;
        margin: var(--space-4) auto var(--space-6);
        overflow-x: auto;
        padding: var(--space-1);
    }
    .settings-tabs :global([role='tablist']) { min-width: 30rem; }
    .settings-tabs :global([role='tab']) {
        font-size: var(--font-size-sm);
        padding-block: var(--space-3);
        min-width: max-content;
        flex-basis: auto;
    }
    .settings-group {
        display: grid;
        grid-template-columns: minmax(14rem, 0.8fr) minmax(0, 1.5fr);
        gap: var(--space-6);
        margin-block: var(--space-5);
        align-items: start;
    }
    .group-description h2 {
        font-size: var(--font-size-lg);
        margin-bottom: var(--space-2);
    }
    .group-description p,
    small {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
    .settings-card {
        list-style: none;
        margin: 0;
        padding: var(--space-2) var(--space-5);
        border: var(--border);
        border-radius: var(--corner-lg);
        background: var(--color-surface-raised);
    }
    li {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.8fr);
        gap: var(--space-2) var(--space-4);
        padding-block: var(--space-4);
    }
    li + li { border-top: var(--border); }
    h3 {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        margin: 0 0 var(--space-1);
    }
    small { display: block; }
    .setting-value,
    small { overflow-wrap: anywhere; }
    .setting-value { margin: 0; }
    .setting-actions {
        grid-column: 1 / -1;
        display: flex;
        justify-content: end;
        flex-wrap: wrap;
        gap: var(--space-2);
    }
    @media (--bp-md-and-smaller) {
        .settings-group { grid-template-columns: minmax(0, 1fr); gap: var(--space-3); }
    }
    @media (--bp-xs) {
        .settings-card { padding-inline: var(--space-3); }
        li { grid-template-columns: minmax(0, 1fr); }
    }
</style>
