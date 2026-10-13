<!--
  @component System settings, edited in place. Every setting shows its control
  directly in the list; changes collect in a draft and are written together
  from the save bar. The brand color, corner style, logo and logo height
  preview on the running page while they are edited. Only list-valued settings still open the editor
  dialog.
-->
<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import { onDestroy } from 'svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import Slider from '$lib/components/ui/slider/Slider.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { AdminFieldSchema } from '../schemas/admin-content.js';
    import { settingsTabs } from '../settings.js';
    import type { AdminSettingResource } from '../schemas/resources/admin-settings.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    import { useFieldLabel } from '../forms/labels.js';
    import { controlFor } from '../forms/controls.js';
    import { settingsSchemas } from '../forms/schemas.js';
    import { issueMessage } from '../forms/validationMessages.js';
    import z from 'zod';
    import {
        applyBrandFont,
        applyBrandTheme,
        FONT_FORMATS,
        FONT_MAX_BYTES,
        isBrandColor,
        isVariableFontFile,
        STATUS_COLORS
    } from '../brandTheme.js';
    import { svgPreview } from '../providerIcons.js';

    const app = useApp();
    const translator = useTranslator();
    const { __ } = translator;
    const fieldLabel = useFieldLabel();
    const columns: AdminColumn<AdminSettingResource>[] = [];
    const valueField = (row: AdminSettingResource) =>
        AdminFieldSchema.parse({
            key: 'value',
            type:
                row.options.length ? 'select'
                : row.kind === 'string' ? 'text'
                : row.kind,
            options: row.options,
            required: row.kind !== 'string'
        });
    const version = (row: AdminSettingResource) => ({ headers: { 'If-Match': `"${row._version}"` } });
    const records = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-settings', { query, signal }),
        {
            resettable: true,
            editFields: (row) => [valueField(row)],
            save: async (values, row) => {
                if (!row) throw new Error(__('admin.errors.save'));
                return app.restApi.updateResource('admin-settings', row.id, values, version(row));
            },
            remove: (row) => app.restApi.deleteResource('admin-settings', row.id, version(row))
        }
    );
    const uid = $props.id();
    let activeTab = $state('system');
    const tabs = $derived(
        settingsTabs.map((tab) => ({
            key: tab.key,
            label: __('admin.settings_tabs.' + tab.key),
            id: `${uid}-tab-${tab.key}`,
            panelId: `${uid}-panel-${tab.key}`
        }))
    );

    /** Edited values by setting key; an entry equal to the stored value is not a change. */
    let draft = $state<Record<string, unknown>>({});
    let errors = $state<Record<string, string>>({});
    let saving = $state(false);
    const rowsByKey = $derived(new Map(records.rows.map((row) => [row.key, row])));
    // An unset text setting is null on the server and an empty string in its input.
    const same = (a: unknown, b: unknown) => (a ?? '') === (b ?? '');
    const current = (row: AdminSettingResource) => (row.key in draft ? draft[row.key] : row.value);
    const dirty = $derived(
        Object.keys(draft).filter((key) => {
            const row = rowsByKey.get(key);
            return !!row && !same(draft[key], row.value);
        })
    );
    const locked = $derived(records.loading || records.busy || saving);

    function set(row: AdminSettingResource, value: unknown) {
        draft[row.key] = value;
        delete errors[row.key];
    }
    function discard() {
        draft = {};
        errors = {};
    }
    async function save() {
        const keys = [...dirty];
        const invalid: Record<string, string> = {};
        for (const key of keys) {
            const result = settingsSchemas[key].safeParse(draft[key]);
            if (!result.success) invalid[key] = issueMessage(result.error.issues[0], translator);
        }
        errors = invalid;
        if (Object.keys(invalid).length) return;
        saving = true;
        let failure = '';
        try {
            for (const key of keys) {
                const row = rowsByKey.get(key)!;
                await app.restApi.updateResource('admin-settings', row.id, { value: draft[key] }, version(row));
            }
        } catch (error) {
            failure = error instanceof Error ? error.message : __('admin.errors.save');
        }
        // Re-read either way: settings saved before a failure are no longer changes.
        await records.revalidate();
        if (failure) records.error = failure;
        else {
            draft = {};
            records.notice = __('admin.saved');
            // Names, links and the logo reach the rest of the app without a reload.
            await app.config.refresh().catch(() => {});
        }
        saving = false;
    }

    function display(value: unknown): string {
        if (value === null || value === undefined || value === '') return __('admin.settings_empty');
        if (typeof value === 'boolean') return __(value ? 'admin.yes' : 'admin.no');
        if (Array.isArray(value)) return value.length ? value.join(', ') : __('admin.settings_empty');
        return String(value);
    }
    const text = (row: AdminSettingResource) => String(current(row) ?? '');
    const control = (row: AdminSettingResource) => controlFor('settings', valueField(row), {}, row);
    /** Settings holding an uploaded SVG. */
    const uploads = ['BRAND_LOGO', 'BRAND_FAVICON'];
    /** The tab icon in use while none is uploaded. */
    /** An installation without a `favicon.ico` has nothing to show until an icon is uploaded. */
    let defaultFaviconMissing = $state(false);
    // Loaded from the host the page is open on; the configured base URL can be another host.
    const defaultFavicon = '/favicon.ico';
    async function upload(row: AdminSettingResource, input: HTMLInputElement) {
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;
        // The server checks the drawing itself on save; this only catches the obvious early.
        if (!file.name.toLowerCase().endsWith('.svg') || file.size > 262144) {
            errors[row.key] = __('admin.icons.invalid');
            return;
        }
        set(row, await file.text());
    }

    let uploadingFont = $state(false);
    async function uploadFont(row: AdminSettingResource, input: HTMLInputElement) {
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;
        // The server decides from the file's content; this only catches the obvious early.
        if (!(file.name.split('.').pop()!.toLowerCase() in FONT_FORMATS) || file.size > FONT_MAX_BYTES) {
            errors[row.key] = __('admin.fonts.invalid');
            return;
        }
        const body = new FormData();
        body.append('font', file);
        uploadingFont = true;
        try {
            // The file is stored right away; it takes effect once the setting is saved.
            const result = await app.restApi.postToResourceAction('admin-settings', 'actions/font-upload', body, {
                schema: z.object({ font: z.object({ file: z.string(), name: z.string(), variable: z.boolean() }) })
            });
            set(row, result.font.file);
        } catch {
            errors[row.key] = __('admin.fonts.upload_failed');
        } finally {
            uploadingFont = false;
        }
    }
    const fontUrl = (file: string) => app.uriBuilder.jsonApiUri('brand-fonts', file);
    const fontValue = (read: (row: AdminSettingResource) => unknown) => {
        const font = rowsByKey.get('BRAND_FONT');
        return font && read(font);
    };

    /** Height the logo has while none is set: the wordmark is smaller than an uploaded logo. */
    const ownLogoHeight = () => {
        const logo = rowsByKey.get('BRAND_LOGO');
        return logo && current(logo) ? 28 : 14;
    };

    /** The status a color setting belongs to, e.g. `error`. */
    const statusOf = (key: string) => Object.keys(STATUS_COLORS).find((status) => key === `BRAND_COLOR_${status.toUpperCase()}`);
    const statusValues = (read: (row: AdminSettingResource) => unknown) =>
        Object.fromEntries(
            Object.keys(STATUS_COLORS).map((status) => {
                const row = rowsByKey.get(`BRAND_COLOR_${status.toUpperCase()}`);
                return [status, row && read(row)];
            })
        );

    // Colors, corners and logo height show on the running page while they are edited.
    const brand = () => {
        const value = (key: string) => {
            const row = rowsByKey.get(key);
            return row ? current(row) : undefined;
        };
        return {
            accentColor: value('BRAND_ACCENT_COLOR'),
            cornerStyle: value('BRAND_CORNER_STYLE'),
            logoHeight: value('BRAND_LOGO_HEIGHT'),
            statusColors: statusValues(current)
        };
    };
    $effect(() => {
        if (rowsByKey.size) applyBrandTheme(brand());
    });
    $effect(() => {
        if (rowsByKey.size) applyBrandFont(fontValue(current), fontUrl);
    });
    // The logo previews where it lives: the sidebar reads it from the app configuration.
    const config = app.config.get();
    $effect(() => {
        const logo = rowsByKey.get('BRAND_LOGO');
        if (logo && config.branding) config.branding.logoUrl = svgPreview(current(logo)) ?? null;
    });
    onDestroy(() => {
        // Leaving with unsaved changes returns the page to the stored theme and logo.
        if (!rowsByKey.size) return;
        if (dirty.includes('BRAND_LOGO')) void app.config.refresh().catch(() => {});
        applyBrandFont(fontValue((row) => row.value), fontUrl);
        applyBrandTheme({
            accentColor: rowsByKey.get('BRAND_ACCENT_COLOR')?.value,
            cornerStyle: rowsByKey.get('BRAND_CORNER_STYLE')?.value,
            logoHeight: rowsByKey.get('BRAND_LOGO_HEIGHT')?.value,
            statusColors: statusValues((row) => row.value)
        });
    });
</script>

<AdminPage
    workspace="settings"
    recordSet={records}
    hint={__('admin.settings_hint')}
>
    <div class="settings-tabs">
        <Tabs
            items={tabs}
            bind:value={activeTab}
            aria-label={__('admin.sections.settings')}
        />
    </div>
    {#each settingsTabs as tab (tab.key)}
        <div
            id={`${uid}-panel-${tab.key}`}
            role="tabpanel"
            aria-labelledby={`${uid}-tab-${tab.key}`}
            aria-busy={records.loading}
            tabindex="0"
            hidden={activeTab !== tab.key}
        >
            {#each tab.groups as group (group.key)}
                <section
                    class="settings-group"
                    aria-labelledby={`${uid}-group-${group.key}`}
                >
                    <div class="group-description">
                        <h2 id={`${uid}-group-${group.key}`}>{__('admin.settings_groups.' + group.key + '.title')}</h2>
                        <p>{__('admin.settings_groups.' + group.key + '.description')}</p>
                    </div>
                    <ul class="settings-list">
                        {#each group.settings as key (key)}
                            {@const row = rowsByKey.get(key)}
                            {#if row}
                                {@const id = `${uid}-${key}`}
                                {@const label = __('admin.settings_labels.' + key)}
                                {@const value = current(row)}
                                <li>
                                    <div class="setting-label">
                                        <label id={`${id}-label`} for={id}>{label}</label>
                                        {#if row.source === 'database'}
                                            <small
                                                >{__('admin.fields.default')}: {row.options.length ?
                                                    fieldLabel(display(row.default))
                                                :   display(row.default)}</small
                                            >
                                        {/if}
                                    </div>
                                    <div class="setting-control">
                                        {#if row.kind === 'json'}
                                            <p class="setting-value">{display(row.value)}</p>
                                        {:else if statusOf(key)}
                                            <div class="color">
                                                <span class="result" style:background={`var(--color-${statusOf(key)})`} aria-hidden="true"></span>
                                                <Input
                                                    {id}
                                                    placeholder={String(row.default ?? '')}
                                                    bind:value={() => text(row), (next) => set(row, next)}
                                                    aria-invalid={!!errors[key]}
                                                    disabled={locked}
                                                    autocomplete="off"
                                                    spellcheck={false}
                                                />
                                            </div>
                                        {:else if key === 'BRAND_ACCENT_COLOR'}
                                            <div class="color">
                                                <input
                                                    type="color"
                                                    aria-label={__('admin.settings_pick_color')}
                                                    value={isBrandColor(value) ? value : String(row.default ?? '#1139d7')}
                                                    oninput={(event) => set(row, event.currentTarget.value)}
                                                    disabled={locked}
                                                />
                                                <Input
                                                    {id}
                                                    placeholder={String(row.default ?? '')}
                                                    bind:value={() => text(row), (next) => set(row, next)}
                                                    aria-invalid={!!errors[key]}
                                                    disabled={locked}
                                                    autocomplete="off"
                                                    spellcheck={false}
                                                />
                                            </div>
                                        {:else if key === 'BRAND_FONT'}
                                            <div class="upload">
                                                {#if value}
                                                    <span class="sample">{__('admin.fonts.sample')}</span>
                                                {/if}
                                                <input
                                                    {id}
                                                    class="u-sr-only"
                                                    type="file"
                                                    accept={Object.keys(FONT_FORMATS)
                                                        .map((extension) => '.' + extension)
                                                        .join(',')}
                                                    tabindex="-1"
                                                    disabled={locked || uploadingFont}
                                                    onchange={(event) => void uploadFont(row, event.currentTarget)}
                                                />
                                                <Button
                                                    variant="stroke"
                                                    size="sm"
                                                    disabled={locked || uploadingFont}
                                                    aria-label={`${label}: ${__(value ? 'admin.fonts.replace' : 'admin.fonts.upload')}`}
                                                    onclick={() => document.getElementById(id)?.click()}
                                                    >{__(value ? 'admin.fonts.replace' : 'admin.fonts.upload')}</Button
                                                >
                                            </div>
                                            {#if isVariableFontFile(value)}
                                                <small>{__('admin.fonts.variable')}</small>
                                            {/if}
                                        {:else if uploads.includes(key)}
                                            <!-- Always shows the asset in use: the upload, or the built-in one it would replace. -->
                                            <div class="upload">
                                                {#if key === 'BRAND_LOGO'}
                                                    <span class="preview">
                                                        <HawkLogo src={svgPreview(value)} aria-hidden="true" />
                                                    </span>
                                                {:else if value || !defaultFaviconMissing}
                                                    <span class="preview">
                                                        <img
                                                            src={svgPreview(value) ?? defaultFavicon}
                                                            alt=""
                                                            onerror={() => (defaultFaviconMissing = !value)}
                                                        />
                                                    </span>
                                                {/if}
                                                <input
                                                    {id}
                                                    class="u-sr-only"
                                                    type="file"
                                                    accept=".svg,image/svg+xml"
                                                    tabindex="-1"
                                                    disabled={locked}
                                                    onchange={(event) => void upload(row, event.currentTarget)}
                                                />
                                                <Button
                                                    variant="stroke"
                                                    size="sm"
                                                    disabled={locked}
                                                    aria-label={`${label}: ${__(value ? 'admin.settings_replace_svg' : 'admin.settings_upload_svg')}`}
                                                    onclick={() => document.getElementById(id)?.click()}
                                                    >{__(value ? 'admin.settings_replace_svg' : 'admin.settings_upload_svg')}</Button
                                                >
                                            </div>
                                        {:else if key === 'BRAND_LOGO_HEIGHT'}
                                            <div class="range">
                                                <Slider
                                                    min={12}
                                                    max={36}
                                                    step={1}
                                                    value={Number(value ?? ownLogoHeight())}
                                                    onValueChange={(next: number) => set(row, next)}
                                                    aria-labelledby={`${id}-label`}
                                                    disabled={locked}
                                                />
                                                <span>{__('admin.settings_pixels', { value: String(value ?? ownLogoHeight()) })}</span>
                                            </div>
                                        {:else if key === 'BRAND_CORNER_STYLE'}
                                            <Tabs
                                                mode="radio"
                                                items={row.options.map((option) => ({
                                                    key: option.value,
                                                    label: fieldLabel(option.label)
                                                }))}
                                                value={String(value)}
                                                onChange={(next) => set(row, next)}
                                                aria-label={label}
                                                disabled={locked}
                                            />
                                        {:else if row.options.length}
                                            <SingleSelect
                                                triggerProps={{ id, 'aria-labelledby': `${id}-label ${id}` }}
                                                disabled={locked}
                                                items={row.options.map((option) => ({
                                                    value: option.value,
                                                    label: fieldLabel(option.label)
                                                }))}
                                                value={String(value ?? '')}
                                                onValueChange={(next: string) => set(row, next)}
                                            />
                                        {:else if row.kind === 'boolean'}
                                            <Switch
                                                {id}
                                                aria-labelledby={`${id}-label`}
                                                bind:checked={() => value === true, (next) => set(row, next)}
                                                disabled={locked}
                                            />
                                        {:else}
                                            {@const input = control(row)}
                                            <Input
                                                    {id}
                                                    type={input.type === 'number' || input.type === 'url' ? input.type : 'text'}
                                                    min={input.min}
                                                    max={input.max}
                                                    bind:value={
                                                        () => text(row),
                                                        (next) =>
                                                            set(
                                                                row,
                                                                input.type !== 'number' ? next
                                                                : next === '' ? undefined
                                                                : Number(next)
                                                            )
                                                    }
                                                    aria-invalid={!!errors[key]}
                                                    disabled={locked}
                                                    autocomplete="off"
                                                />
                                        {/if}
                                        {#if errors[key]}
                                            <p class="setting-error" role="alert">{errors[key]}</p>
                                        {/if}
                                    </div>
                                    {#if row.kind === 'json' || !same(value, row.default)}
                                    <div class="setting-actions">
                                        {#if row.kind === 'json'}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={locked}
                                                aria-label={__('admin.settings_edit', { name: label })}
                                                onclick={(event) => records.edit(row, event.currentTarget)}
                                                >{__('admin.edit')}</Button
                                            >
                                            {#if row.source === 'database'}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={locked}
                                                    aria-label={__('admin.settings_reset', { name: label })}
                                                    onclick={(event) =>
                                                        records.remove(
                                                            row,
                                                            event.currentTarget.parentElement?.querySelector(
                                                                'button'
                                                            ) ?? event.currentTarget
                                                        )}>{__('admin.settings_use_default')}</Button
                                                >
                                            {/if}
                                        {:else}
                                            <!-- Saving the default removes the override, so this needs no request of its own. -->
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={locked}
                                                aria-label={__('admin.settings_reset', { name: label })}
                                                onclick={() => set(row, row.default)}
                                                >{__('admin.settings_use_default')}</Button
                                            >
                                        {/if}
                                    </div>
                                    {/if}
                                </li>
                            {/if}
                        {/each}
                    </ul>
                </section>
            {/each}
        </div>
    {/each}
    {#if dirty.length}
        <div class="save-bar" role="group" aria-label={__('admin.settings_unsaved', { count: String(dirty.length) })}>
            <p>{__('admin.settings_unsaved', { count: String(dirty.length) })}</p>
            <Button variant="ghost" disabled={saving} onclick={discard}>{__('admin.discard')}</Button>
            <Button disabled={saving} onclick={() => void save()}>{__('admin.save')}</Button>
        </div>
    {/if}
</AdminPage>

<style>
    .settings-tabs {
        max-width: 48rem;
        margin: var(--space-4) auto var(--space-6);
        overflow-x: auto;
        padding: var(--space-1);
    }
    .settings-tabs :global([role='tablist']) {
        min-width: 42rem;
    }
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
        font-size: var(--font-size-xs);
    }
    .group-description p {
        font-size: var(--font-size-sm);
    }
    .settings-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    li {
        display: grid;
        grid-template-columns: minmax(0, 0.7fr) minmax(0, 1fr);
        align-items: center;
        gap: var(--space-2) var(--space-4);
        padding-block: var(--space-3);
    }
    li + li {
        border-top: var(--border);
    }
    label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
    }
    small {
        display: block;
        margin-top: var(--space-1);
        overflow-wrap: anywhere;
    }
    .setting-control {
        display: grid;
        gap: var(--space-1);
        min-width: 0;
        justify-items: start;
    }
    .setting-control > :global(*) {
        max-width: 100%;
    }
    /* Wide enough that every option fits: the tabs share the width equally and would
       otherwise shrink to the average label, cutting the longest one off. */
    .setting-control > :global(.tabs) {
        width: 18rem;
    }
    .range {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 4rem;
        align-items: center;
        gap: var(--space-3);
        width: 100%;
        font-size: var(--font-size-sm);
        font-variant-numeric: tabular-nums;
    }
    .color,
    .upload {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        width: 100%;
    }
    .color input[type='color'] {
        flex: none;
        box-sizing: border-box;
        width: 2.75rem;
        height: 2.75rem;
        padding: 0;
        border: var(--border);
        border-radius: var(--corner-md);
        background: none;
        overflow: hidden;
        cursor: pointer;
    }
    .color input[type='color']::-webkit-color-swatch-wrapper {
        padding: 0;
    }
    .color input[type='color']::-webkit-color-swatch {
        border: 0;
    }
    .color input[type='color']::-moz-color-swatch {
        border: 0;
    }
    .sample {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-sm);
    }
    .result {
        flex: none;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: var(--corner-full);
    }
    .preview {
        flex: none;
        box-sizing: border-box;
        display: grid;
        place-items: center;
        /* As tall as a sidebar row plus its inset, so the logo previews at its real size. */
        height: 3.25rem;
        padding: var(--space-2) var(--space-3);
        border-radius: var(--corner-md);
        background: var(--color-surface);
    }
    /* A fixed size, not a percentage: inside the grid a percentage height resolves
       against the image's own size and lets a large icon grow out of the box. */
    .preview > img {
        display: block;
        width: 2.25rem;
        height: 2.25rem;
        object-fit: contain;
    }
    .setting-value {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: var(--font-size-sm);
    }
    .setting-error {
        margin: 0;
        color: var(--color-error);
        font-size: var(--font-size-xs);
    }
    .setting-actions {
        grid-column: 2;
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        margin-inline-start: calc(-1 * var(--space-2));
    }
    .save-bar {
        position: sticky;
        bottom: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: end;
        gap: var(--space-2);
        margin-top: var(--space-4);
        padding-block: var(--space-3);
        border-top: var(--border);
        background: var(--color-bg);
    }
    .save-bar p {
        margin: 0 auto 0 0;
        font-size: var(--font-size-sm);
    }
    @media (--bp-md-and-smaller) {
        .settings-group {
            grid-template-columns: minmax(0, 1fr);
            gap: var(--space-3);
        }
    }
    @media (--bp-xs) {
        li {
            grid-template-columns: minmax(0, 1fr);
        }
        .setting-actions {
            grid-column: 1;
        }
    }
</style>
