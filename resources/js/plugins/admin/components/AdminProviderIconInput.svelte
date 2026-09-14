<script lang="ts">
    import { onDestroy, tick } from 'svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { record } from '../forms/controls.js';
    import { loadProviderIcons, uploadProviderIcon, svgPreview, type SvglIcon } from '../providerIcons.js';

    let {
        id,
        label,
        value,
        onchange,
        onblur,
        onBusyChange,
        disabled = false,
        error
    }: {
        id: string;
        label: string;
        value: unknown;
        onchange: (value: unknown) => void;
        onBusyChange: (busy: boolean) => void;
        onblur: () => void;
        disabled?: boolean;
        error?: string;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    let mode = $state('svgl');
    let search = $state('');
    let icons = $state<SvglIcon[] | null>(null);
    let loading = $state(false);
    let uploading = $state(false);
    let failure = $state('');
    let status = $state('');
    let editing = $state(false);
    const expanded = $derived(!value || editing);
    const requests = new AbortController();
    onDestroy(() => {
        requests.abort();
        catalogueRequest?.abort();
    });
    let catalogueRequest: AbortController | undefined;
    let selectedPreview = $state<SvglIcon | null>(null);
    $effect(() => {
        const term = search.trim();
        catalogueRequest?.abort();
        const controller = new AbortController();
        catalogueRequest = controller;
        loading = true;
        icons = null;
        const timer = setTimeout(() => void load(term, controller), term ? 250 : 0);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    });
    const selected = $derived(record(value));
    const results = $derived(icons ?? []);
    const selectedCatalogueIcon = $derived(
        selectedPreview?.id === selected.svgl_id ?
            selectedPreview
        :   icons?.find((icon) => icon.id === selected.svgl_id && icon.title === selected.title)
    );
    const light = $derived(svgPreview(selected.svg) ?? selectedCatalogueIcon?.light);
    const dark = $derived(svgPreview(selected.svg_dark) ?? selectedCatalogueIcon?.dark ?? light);

    async function load(term = search.trim(), controller = new AbortController()) {
        if (catalogueRequest !== controller) catalogueRequest?.abort();
        catalogueRequest = controller;
        loading = true;
        failure = '';
        try {
            const result = await loadProviderIcons(app, controller.signal, term);
            if (!controller.signal.aborted) icons = result;
        } catch {
            if (!controller.signal.aborted) failure = __('admin.icons.unavailable');
        } finally {
            if (!controller.signal.aborted) loading = false;
        }
    }
    async function upload(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;
        failure = '';
        status = '';
        if (!file.name.toLowerCase().endsWith('.svg') || file.size > 262144) {
            failure = __('admin.icons.invalid');
            input.value = '';
            return;
        }
        uploading = true;
        onBusyChange(true);
        try {
            const icon = await uploadProviderIcon(app, file, requests.signal);
            if (!requests.signal.aborted) {
                onchange(icon);
                status = __('admin.icons.selected', { name: icon.title });
                void collapse();
            }
        } catch {
            if (!requests.signal.aborted) failure = __('admin.icons.upload_failed');
        } finally {
            uploading = false;
            onBusyChange(false);
            input.value = '';
        }
    }
    function choose(icon: SvglIcon) {
        selectedPreview = icon;
        onchange({ source: 'svgl', svgl_id: icon.id, title: icon.title });
        failure = '';
        status = __('admin.icons.selected', { name: icon.title });
        void collapse();
    }
    async function collapse() {
        editing = false;
        await tick();
        document.getElementById(`${id}-change`)?.focus();
    }
    async function open() {
        editing = true;
        await tick();
        document.getElementById(`${id}-${mode}-mode`)?.focus();
    }
</script>

<fieldset
    {id}
    {disabled}
    onfocusout={onblur}
    data-invalid={!!(error || failure)}
    aria-describedby={error || failure ? `${id}-error` : undefined}
>
    <legend>{label}</legend>
    <div class="preview">
        {#if light}
            <figure>
                <div class="sample light">
                    <img
                        src={light}
                        alt=""
                    />
                </div>
                <figcaption>{__('admin.icons.light')}</figcaption>
            </figure>
            <figure>
                <div class="sample dark">
                    <img
                        src={dark}
                        alt=""
                    />
                </div>
                <figcaption>{__('admin.icons.dark')}</figcaption>
            </figure>
        {/if}
        <span>{typeof selected.title === 'string' ? selected.title : __('admin.icons.none')}</span>
        {#if value}
            <Button
                id={`${id}-change`}
                type="button"
                variant="stroke"
                size="sm"
                disabled={disabled || uploading}
                aria-expanded={expanded}
                aria-controls={`${id}-selection`}
                onclick={open}>{__('admin.icons.change')}</Button
            >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={disabled || uploading}
                onclick={() => {
                    onchange(null);
                    selectedPreview = null;
                    failure = '';
                    status = __('admin.icons.removed');
                    void open();
                }}>{__('admin.icons.remove')}</Button
            >
        {/if}
    </div>
    <div
        id={`${id}-selection`}
        hidden={!expanded}
    >
        <Tabs
            items={[
                { key: 'svgl', label: __('admin.icons.svgl'), id: `${id}-svgl-mode`, panelId: `${id}-svgl-panel` },
                { key: 'upload', label: __('admin.icons.upload'), id: `${id}-upload-mode`, panelId: `${id}-upload-panel` }
            ]}
            value={mode}
            onChange={(next) => {
                mode = next;
                failure = '';
            }}
            aria-label={label}
            disabled={disabled || uploading}
        />
        <div
            id={`${id}-upload-panel`}
            role="tabpanel"
            aria-labelledby={`${id}-upload-mode`}
            hidden={mode !== 'upload'}
        >
            <label for={`${id}-file`}>{__('admin.icons.file')}</label>
            <input
                id={`${id}-file`}
                type="file"
                accept=".svg,image/svg+xml"
                onchange={upload}
                disabled={disabled || uploading}
                aria-describedby={error || failure ? `${id}-error` : undefined}
                aria-invalid={mode === 'upload' && !!(error || failure)}
            />
        </div>
        <div
            id={`${id}-svgl-panel`}
            role="tabpanel"
            aria-labelledby={`${id}-svgl-mode`}
            hidden={mode !== 'svgl'}
        >
            <label for={`${id}-search`}>{__('admin.icons.search')}</label>
            <Input
                id={`${id}-search`}
                type="search"
                placeholder={__('admin.icons.search_placeholder')}
                bind:value={search}
                {disabled}
                aria-invalid={mode === 'svgl' && !!error}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            <p role="status">
                {loading ? __('admin.icons.loading')
                : icons ? __('admin.icons.results', { count: String(results.length) })
                : ''}
            </p>
            {#if icons}
                <ul class="icons">
                    {#each results as icon (icon.id)}
                        <li>
                            <button
                                type="button"
                                class="icon"
                                {disabled}
                                aria-pressed={selected.source === 'svgl' &&
                                    selected.svgl_id === icon.id &&
                                    selected.title === icon.title}
                                onclick={() => choose(icon)}
                            >
                                <span class="thumbnail"
                                    ><img
                                        src={icon.light}
                                        alt=""
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                    /></span
                                >
                                <span>{icon.title}</span>
                            </button>
                        </li>
                    {/each}
                </ul>
                {#if results.length === 0}<p>{__('admin.icons.empty')}</p>{/if}
            {:else if !loading}
                <Button
                    type="button"
                    variant="stroke"
                    {disabled}
                    onclick={() => void load()}>{__('admin.icons.retry')}</Button
                >
            {/if}
        </div>
    </div>
    <p
        role="status"
        class:u-sr-only={!uploading}
    >
        {uploading ? __('admin.icons.uploading') : status}
    </p>
    {#if error || failure}<p
            id={`${id}-error`}
            role="alert"
        >
            {error || failure}
        </p>{/if}
</fieldset>

<style>
    fieldset {
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-3);
        min-width: 0;
    }
    legend {
        font-weight: 600;
        font-size: var(--font-size-sm);
        padding-inline: var(--space-1);
    }
    p,
    label,
    figcaption {
        font-size: var(--font-size-sm);
    }
    figcaption {
        color: var(--color-text-muted);
    }
    .preview {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-block: var(--space-3);
    }
    .preview > span {
        overflow-wrap: anywhere;
        min-width: 0;
    }
    figure {
        margin: 0;
        text-align: center;
    }
    .sample {
        width: 4rem;
        height: 4rem;
        display: grid;
        place-items: center;
        border-radius: var(--corner-md);
        border: 1px solid #777;
    }
    .light,
    .thumbnail {
        background: #fff;
    }
    .dark {
        background: #18181b;
    }
    img {
        width: 2rem;
        height: 2rem;
        object-fit: contain;
    }
    [role='tabpanel'] {
        padding-top: var(--space-3);
    }
    label {
        display: block;
        margin-bottom: var(--space-2);
    }
    input[type='file'] {
        max-width: 100%;
    }
    .icons {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr));
        gap: var(--space-2);
        list-style: none;
        padding: var(--space-1);
        max-height: 20rem;
        overflow-y: auto;
    }
    .icon {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
        border: var(--border);
        border-radius: var(--corner-md);
        background: transparent;
        color: inherit;
        padding: var(--space-2);
        cursor: pointer;
        font: inherit;
        font-size: var(--font-size-sm);
        overflow-wrap: anywhere;
    }
    .thumbnail {
        display: grid;
        place-items: center;
        width: 3rem;
        height: 3rem;
        border-radius: var(--corner-sm);
    }
    .icon[aria-pressed='true'] {
        outline: 2px solid var(--color-highlight);
        outline-offset: -2px;
    }
    [role='alert'] {
        color: var(--color-error);
    }
    @media (forced-colors: active) {
        .icon[aria-pressed='true'] {
            outline: 2px solid Highlight;
        }
    }
</style>
