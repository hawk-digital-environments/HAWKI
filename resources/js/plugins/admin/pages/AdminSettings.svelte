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
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    // The onboarding's own emoji, so the list shows each step the way new users see it.
    import { DEFAULT_STEP_EMOJI, welcomeStepEmoji } from '$plugins/auth/pages/welcome/welcomeSteps.js';
    import ArrowUp01Icon from '$lib/components/ui/icons/iconset/ArrowUp01Icon.svelte';
    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useAppName } from '$lib/app/hooks/useAppName.svelte.js';
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
        type FontWeight,
        STATUS_COLORS
    } from '../brandTheme.js';
    import { svgPreview } from '../providerIcons.js';

    const app = useApp();
    const translator = useTranslator();
    const { __ } = translator;
    const fieldLabel = useFieldLabel();
    const appName = useAppName();
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
    // Lists and objects compare by content: a draft is never the same object as the stored value.
    const same = (a: unknown, b: unknown) =>
        (typeof a === 'object' && a !== null) || (typeof b === 'object' && b !== null) ?
            JSON.stringify(a ?? null) === JSON.stringify(b ?? null)
        :   (a ?? '') === (b ?? '');
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
    /** Upload limit of the sign-in background in bytes; `BrandMedia::MAX_KILOBYTES` enforces the same on the server. */
    const MEDIA_MAX_BYTES = 50 * 1024 * 1024;
    const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'mp4', 'm4v', 'webm'];
    let uploadingMedia = $state(false);
    async function uploadMedia(row: AdminSettingResource, input: HTMLInputElement) {
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;
        // The server decides from the file's content; this only catches the obvious early.
        if (!MEDIA_EXTENSIONS.includes(file.name.split('.').pop()!.toLowerCase()) || file.size > MEDIA_MAX_BYTES) {
            errors[row.key] = __('admin.media.invalid');
            return;
        }
        const body = new FormData();
        body.append('media', file);
        uploadingMedia = true;
        try {
            // The file is stored right away; it takes effect once the setting is saved.
            const result = await app.restApi.postToResourceAction('admin-settings', 'actions/media-upload', body, {
                schema: z.object({ media: z.object({ file: z.string(), name: z.string(), kind: z.string() }) })
            });
            set(row, [...backgrounds(row), { source: 'upload', file: result.media.file, credit: null, link: null }]);
        } catch {
            errors[row.key] = __('admin.media.upload_failed');
        } finally {
            uploadingMedia = false;
        }
    }
    /** One sign-in background as the setting stores it (see `BrandMedia::backgrounds()`). */
    interface Background {
        source: 'upload' | 'builtin';
        file: string;
        credit: string | null;
        link: string | null;
    }
    const MAX_BACKGROUNDS = 20;
    const backgrounds = (row: AdminSettingResource) => (Array.isArray(current(row)) ? (current(row) as Background[]) : []);
    function changeBackground(row: AdminSettingResource, index: number, change: Partial<Background> | null) {
        const next = backgrounds(row).map((entry) => ({ ...entry }));
        if (change) Object.assign(next[index], change);
        else next.splice(index, 1);
        set(row, next);
    }
    /** One news entry as the setting stores it (see `LoginNews::entries()`). */
    interface NewsEntry {
        date: string;
        title: string;
        text: string | null;
    }
    const MAX_NEWS = 10;
    /** One onboarding step as the setting stores it (see `Onboarding::steps()`). */
    type OnboardingStep =
        | {
              type: 'builtin';
              step: 'encryption' | 'groups';
              emoji?: string | null;
              title?: string | null;
              text?: string | null;
              action?: string | null;
          }
        | { type: 'custom'; emoji: string | null; title: string; text: string; action: string | null };
    type StepText = 'title' | 'text' | 'action';
    /** A built-in step's field shows its translated default; a custom step's field names what goes in. */
    const stepPlaceholder = (step: OnboardingStep, key: StepText) =>
        step.type === 'builtin' ?
            __(`ui.auth.register.welcome.${step.step}.${key === 'text' ? 'body' : key}`, { appName })
        :   __(`admin.onboarding.${key}`);
    /** Emptying a field of a built-in step returns it to the translation; a custom step keeps what is typed. */
    const stepValue = (step: OnboardingStep, value: string) => (step.type === 'builtin' ? value || null : value);
    const MAX_STEPS = 8;
    const onboardingSteps = (row: AdminSettingResource) =>
        Array.isArray(current(row)) ? (current(row) as OnboardingStep[]) : [];
    function changeStep(row: AdminSettingResource, index: number, change: Record<string, unknown> | null) {
        const next = onboardingSteps(row).map((step) => ({ ...step }));
        if (change) Object.assign(next[index], change);
        else next.splice(index, 1);
        set(row, next);
    }
    function moveStep(row: AdminSettingResource, index: number, offset: -1 | 1) {
        const next = [...onboardingSteps(row)];
        [next[index], next[index + offset]] = [next[index + offset], next[index]];
        set(row, next);
    }
    function addStep(row: AdminSettingResource) {
        set(row, [...onboardingSteps(row), { type: 'custom', emoji: null, title: '', text: '', action: null }]);
    }
    const newsEntries = (row: AdminSettingResource) => (Array.isArray(current(row)) ? (current(row) as NewsEntry[]) : []);
    function changeNews(row: AdminSettingResource, index: number, change: Partial<NewsEntry> | null) {
        const next = newsEntries(row).map((entry) => ({ ...entry }));
        if (change) Object.assign(next[index], change);
        else next.splice(index, 1);
        set(row, next);
    }
    /** New entries start on top, dated today, as the sign-in page lists the newest first. */
    function addNews(row: AdminSettingResource) {
        const today = new Date();
        const date = [today.getFullYear(), today.getMonth() + 1, today.getDate()]
            .map((part) => String(part).padStart(2, '0'))
            .join('-');
        set(row, [{ date, title: '', text: null }, ...newsEntries(row)]);
    }
    const backgroundUrl = (entry: Background) =>
        entry.source === 'upload' ? app.uriBuilder.jsonApiUri('brand-media', entry.file) : `/bg_videos/${entry.file}`;
    const isVideo = (file: unknown) => /\.(?:mp4|webm)$/.test(String(file));
    const fontUrl = (file: string) => app.uriBuilder.jsonApiUri('brand-fonts', file);
    /** The font settings by the weight and style they are uploaded for. */
    const FONT_KEYS: Record<string, { weight: FontWeight; italic: boolean }> = {
        BRAND_FONT: { weight: 400, italic: false },
        BRAND_FONT_500: { weight: 500, italic: false },
        BRAND_FONT_600: { weight: 600, italic: false },
        BRAND_FONT_ITALIC: { weight: 400, italic: true },
        BRAND_FONT_500_ITALIC: { weight: 500, italic: true },
        BRAND_FONT_600_ITALIC: { weight: 600, italic: true }
    };
    const fontValues = (read: (row: AdminSettingResource) => unknown, italic: boolean) =>
        Object.fromEntries(
            Object.entries(FONT_KEYS)
                .filter(([, slot]) => slot.italic === italic)
                .map(([key, slot]) => {
                    const row = rowsByKey.get(key);
                    return [slot.weight, row && read(row)];
                })
        ) as Partial<Record<FontWeight, unknown>>;
    const currentFont = (key: string) => {
        const row = rowsByKey.get(key);
        return row && current(row);
    };
    /**
     * Why a slot cannot take a file yet, as a translation key, or null when it can. Every slot builds
     * on the regular font; the other weights of a style build on its 400 file, and a variable 400 file
     * already provides them.
     */
    const fontBlocked = (key: string): string | null => {
        const slot = FONT_KEYS[key];
        if (!slot || key === 'BRAND_FONT') return null;
        if (!currentFont('BRAND_FONT')) return 'admin.fonts.needs_regular';
        if (slot.weight === 400) return null;
        const base = currentFont(slot.italic ? 'BRAND_FONT_ITALIC' : 'BRAND_FONT');
        if (!base) return 'admin.fonts.needs_italic';
        return isVariableFontFile(base) ? (slot.italic ? 'admin.fonts.covered_italic' : 'admin.fonts.covered') : null;
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
        if (rowsByKey.size) applyBrandFont(fontValues(current, false), fontValues(current, true), fontUrl);
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
        applyBrandFont(fontValues((row) => row.value, false), fontValues((row) => row.value, true), fontUrl);
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
                                        {#if row.source === 'database' && row.kind !== 'json'}
                                            <small
                                                >{__('admin.fields.default')}: {row.options.length ?
                                                    fieldLabel(display(row.default))
                                                :   display(row.default)}</small
                                            >
                                        {/if}
                                    </div>
                                    <div class="setting-control">
                                        {#if key === 'BRAND_ONBOARDING'}
                                            {@const steps = onboardingSteps(row)}
                                            {#if steps.length}
                                                <ol class="backgrounds">
                                                    {#each steps as step, index (index)}
                                                        {@const name = __('admin.onboarding.entry', { number: String(index + 1) })}
                                                        <li class="onboarding-entry">
                                                            <span class="step-emoji-preview" aria-hidden="true"
                                                                >{step.emoji ??
                                                                    (step.type === 'builtin' ? welcomeStepEmoji[step.step] : DEFAULT_STEP_EMOJI)}</span
                                                            >
                                                            <div class="background-fields">
                                                                {#if step.type === 'builtin'}
                                                                    <small>{__('admin.onboarding.builtin')}</small>
                                                                {/if}
                                                                <div class="step-heading">
                                                                    <Input
                                                                        class="step-emoji"
                                                                        aria-label={`${name}: ${__('admin.onboarding.emoji')}`}
                                                                        placeholder={step.type === 'builtin' ? welcomeStepEmoji[step.step] : DEFAULT_STEP_EMOJI}
                                                                        maxlength={16}
                                                                        bind:value={
                                                                            () => step.emoji ?? '',
                                                                            (emoji) => changeStep(row, index, { emoji: emoji || null })
                                                                        }
                                                                        disabled={locked}
                                                                        autocomplete="off"
                                                                    />
                                                                    <Input
                                                                        aria-label={`${name}: ${__('admin.onboarding.title')}`}
                                                                        placeholder={stepPlaceholder(step, 'title')}
                                                                        maxlength={80}
                                                                        bind:value={
                                                                            () => step.title ?? '',
                                                                            (title) => changeStep(row, index, { title: stepValue(step, title) })
                                                                        }
                                                                        disabled={locked}
                                                                        autocomplete="off"
                                                                    />
                                                                </div>
                                                                <Textarea
                                                                    aria-label={`${name}: ${__('admin.onboarding.text')}`}
                                                                    placeholder={stepPlaceholder(step, 'text')}
                                                                    maxlength={600}
                                                                    rows={3}
                                                                    bind:value={
                                                                        () => step.text ?? '',
                                                                        (text) => changeStep(row, index, { text: stepValue(step, text) })
                                                                    }
                                                                    disabled={locked}
                                                                />
                                                                <Input
                                                                    aria-label={`${name}: ${__('admin.onboarding.action')}`}
                                                                    placeholder={stepPlaceholder(step, 'action')}
                                                                    maxlength={40}
                                                                    bind:value={
                                                                        () => step.action ?? '',
                                                                        (action) => changeStep(row, index, { action: action || null })
                                                                    }
                                                                    disabled={locked}
                                                                    autocomplete="off"
                                                                />
                                                            </div>
                                                            <div class="step-actions">
                                                                <ButtonWithTooltip
                                                                    variant="ghost"
                                                                    iconLeft={ArrowUp01Icon}
                                                                    tooltip={__('admin.onboarding.up')}
                                                                    disabled={locked || index === 0}
                                                                    aria-label={`${name}: ${__('admin.onboarding.up')}`}
                                                                    onclick={() => moveStep(row, index, -1)}
                                                                />
                                                                <ButtonWithTooltip
                                                                    variant="ghost"
                                                                    iconLeft={ArrowDown01Icon}
                                                                    tooltip={__('admin.onboarding.down')}
                                                                    disabled={locked || index === steps.length - 1}
                                                                    aria-label={`${name}: ${__('admin.onboarding.down')}`}
                                                                    onclick={() => moveStep(row, index, 1)}
                                                                />
                                                                <ButtonWithTooltip
                                                                    variant="ghost"
                                                                    iconLeft={Delete02Icon}
                                                                    tooltip={__('admin.onboarding.remove')}
                                                                    disabled={locked}
                                                                    aria-label={`${name}: ${__('admin.onboarding.remove')}`}
                                                                    onclick={() => changeStep(row, index, null)}
                                                                />
                                                            </div>
                                                        </li>
                                                    {/each}
                                                </ol>
                                            {:else}
                                                <p class="setting-value">{__('admin.onboarding.empty')}</p>
                                            {/if}
                                            <small>{__('admin.onboarding.passkey_last')}</small>
                                            <Button
                                                variant="stroke"
                                                size="sm"
                                                disabled={locked || steps.length >= MAX_STEPS}
                                                onclick={() => addStep(row)}>{__('admin.onboarding.add')}</Button
                                            >
                                        {:else if key === 'BRAND_LOGIN_NEWS'}
                                            {@const entries = newsEntries(row)}
                                            {#if entries.length}
                                                <ul class="backgrounds">
                                                    {#each entries as entry, index (index)}
                                                        {@const name = __('admin.news.entry', { number: String(index + 1) })}
                                                        <li class="news-entry">
                                                            <div class="background-fields">
                                                                <Input
                                                                    type="date"
                                                                    aria-label={`${name}: ${__('admin.news.date')}`}
                                                                    bind:value={() => entry.date, (date) => changeNews(row, index, { date })}
                                                                    disabled={locked}
                                                                />
                                                                <Input
                                                                    aria-label={`${name}: ${__('admin.news.title')}`}
                                                                    placeholder={__('admin.news.title')}
                                                                    maxlength={80}
                                                                    bind:value={() => entry.title, (title) => changeNews(row, index, { title })}
                                                                    disabled={locked}
                                                                    autocomplete="off"
                                                                />
                                                                <Textarea
                                                                    aria-label={`${name}: ${__('admin.news.text')}`}
                                                                    placeholder={__('admin.news.text')}
                                                                    maxlength={280}
                                                                    rows={2}
                                                                    bind:value={
                                                                        () => entry.text ?? '',
                                                                        (text) => changeNews(row, index, { text: text || null })
                                                                    }
                                                                    disabled={locked}
                                                                />
                                                            </div>
                                                            <div class="background-remove">
                                                                <ButtonWithTooltip
                                                                    variant="ghost"
                                                                    iconLeft={Delete02Icon}
                                                                    tooltip={__('admin.news.remove')}
                                                                    disabled={locked}
                                                                    aria-label={`${name}: ${__('admin.news.remove')}`}
                                                                    onclick={() => changeNews(row, index, null)}
                                                                />
                                                            </div>
                                                        </li>
                                                    {/each}
                                                </ul>
                                            {:else}
                                                <p class="setting-value">{__('admin.news.empty')}</p>
                                            {/if}
                                            <Button
                                                variant="stroke"
                                                size="sm"
                                                disabled={locked || entries.length >= MAX_NEWS}
                                                onclick={() => addNews(row)}>{__('admin.news.add')}</Button
                                            >
                                        {:else if key === 'BRAND_LOGIN_BACKGROUNDS'}
                                            {@const entries = backgrounds(row)}
                                            {#if entries.length}
                                                <ul class="backgrounds">
                                                    {#each entries as entry, index (index)}
                                                        {@const name = __('admin.media.entry', { number: String(index + 1) })}
                                                        <li>
                                                            <span class="preview media">
                                                                {#if isVideo(entry.file)}
                                                                    <video src={backgroundUrl(entry)} muted playsinline preload="metadata" aria-hidden="true"></video>
                                                                {:else}
                                                                    <img src={backgroundUrl(entry)} alt="" />
                                                                {/if}
                                                            </span>
                                                            <div class="background-fields">
                                                                <Input
                                                                    aria-label={`${name}: ${__('admin.media.credit')}`}
                                                                    placeholder={__('admin.media.credit')}
                                                                    bind:value={
                                                                        () => entry.credit ?? '',
                                                                        (credit) => changeBackground(row, index, { credit: credit || null })
                                                                    }
                                                                    disabled={locked}
                                                                    autocomplete="off"
                                                                />
                                                                <Input
                                                                    type="url"
                                                                    aria-label={`${name}: ${__('admin.media.link')}`}
                                                                    placeholder={__('admin.media.link')}
                                                                    bind:value={
                                                                        () => entry.link ?? '',
                                                                        (link) => changeBackground(row, index, { link: link || null })
                                                                    }
                                                                    disabled={locked}
                                                                    autocomplete="off"
                                                                />
                                                            </div>
                                                            <!-- As tall as the first field, so the button sits centered beside it. -->
                                                            <div class="background-remove">
                                                                <ButtonWithTooltip
                                                                    variant="ghost"
                                                                    iconLeft={Delete02Icon}
                                                                    tooltip={__('admin.media.remove')}
                                                                    disabled={locked}
                                                                    aria-label={`${name}: ${__('admin.media.remove')}`}
                                                                    onclick={() => changeBackground(row, index, null)}
                                                                />
                                                            </div>
                                                        </li>
                                                    {/each}
                                                </ul>
                                            {:else}
                                                <p class="setting-value">{__('admin.media.empty')}</p>
                                            {/if}
                                            <input
                                                {id}
                                                class="u-sr-only"
                                                type="file"
                                                accept={MEDIA_EXTENSIONS.map((extension) => '.' + extension).join(',')}
                                                tabindex="-1"
                                                disabled={locked || uploadingMedia}
                                                onchange={(event) => void uploadMedia(row, event.currentTarget)}
                                            />
                                            <Button
                                                variant="stroke"
                                                size="sm"
                                                disabled={locked || uploadingMedia || entries.length >= MAX_BACKGROUNDS}
                                                onclick={() => document.getElementById(id)?.click()}
                                                >{__('admin.media.add')}</Button
                                            >
                                        {:else if row.kind === 'json'}
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
                                        {:else if FONT_KEYS[key]}
                                            <div class="upload">
                                                {#if value}
                                                    <span class="sample" style:font-weight={FONT_KEYS[key].weight} style:font-style={FONT_KEYS[key].italic ? 'italic' : undefined}>{__('admin.fonts.sample')}</span>
                                                {/if}
                                                <input
                                                    {id}
                                                    class="u-sr-only"
                                                    type="file"
                                                    accept={Object.keys(FONT_FORMATS)
                                                        .map((extension) => '.' + extension)
                                                        .join(',')}
                                                    tabindex="-1"
                                                    disabled={locked || uploadingFont || !!fontBlocked(key)}
                                                    onchange={(event) => void uploadFont(row, event.currentTarget)}
                                                />
                                                <Button
                                                    variant="stroke"
                                                    size="sm"
                                                    disabled={locked || uploadingFont || !!fontBlocked(key)}
                                                    aria-label={`${label}: ${__(value ? 'admin.fonts.replace' : 'admin.fonts.upload')}`}
                                                    onclick={() => document.getElementById(id)?.click()}
                                                    >{__(value ? 'admin.fonts.replace' : 'admin.fonts.upload')}</Button
                                                >
                                            </div>
                                            {#if isVariableFontFile(value)}
                                                <small>{__('admin.fonts.variable')}</small>
                                            {/if}
                                            {#if fontBlocked(key)}
                                                <small>{__(fontBlocked(key)!)}</small>
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
                                            <div class="segmented" style:--options={row.options.length}>
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
                                            </div>
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
                                    {#if (row.kind === 'json' && !['BRAND_LOGIN_BACKGROUNDS', 'BRAND_LOGIN_NEWS', 'BRAND_ONBOARDING'].includes(key)) || !same(value, row.default)}
                                    <div class="setting-actions">
                                        {#if row.kind === 'json' && !['BRAND_LOGIN_BACKGROUNDS', 'BRAND_LOGIN_NEWS', 'BRAND_ONBOARDING'].includes(key)}
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
    .settings-list > li {
        display: grid;
        grid-template-columns: minmax(0, 0.7fr) minmax(0, 1fr);
        align-items: center;
        gap: var(--space-2) var(--space-4);
        padding-block: var(--space-3);
    }
    .settings-list > li + li {
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
    .segmented {
        width: min(100%, calc(var(--options) * 7.5rem));
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
    .color {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        width: 100%;
    }
    /* Preview on top, its button below: the same order as the background list and its add button. */
    .upload {
        display: grid;
        justify-items: start;
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
    /* As large as the brand color's picker above, so the color column lines up. */
    .result {
        flex: none;
        box-sizing: border-box;
        width: 2.75rem;
        height: 2.75rem;
        border: var(--border);
        border-radius: var(--corner-full);
    }
    .preview {
        flex: none;
        box-sizing: border-box;
        display: grid;
        place-items: center;
        /* Square, and as tall as a background's two fields beside it. */
        width: 6rem;
        height: 6rem;
        padding: var(--space-3);
        border-radius: var(--corner-md);
        background: var(--color-surface);
    }
    .backgrounds {
        display: grid;
        gap: var(--space-3);
        width: 100%;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .backgrounds > li {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: start;
        gap: var(--space-3);
        padding: 0;
        border: 0;
    }
    /* A news entry has no thumbnail: fields and the remove button only. */
    .backgrounds > li.news-entry {
        grid-template-columns: minmax(0, 1fr) auto;
    }
    .backgrounds > li.news-entry + li.news-entry {
        padding-top: var(--space-3);
        border-top: var(--border);
    }
    /* Emoji, the step itself, then order and removal. */
    .backgrounds > li.onboarding-entry {
        grid-template-columns: auto minmax(0, 1fr) auto;
    }
    .backgrounds > li.onboarding-entry + li.onboarding-entry {
        padding-top: var(--space-3);
        border-top: var(--border);
    }
    /* The tinted circle of the onboarding, at the height of a field. */
    .step-emoji-preview {
        display: grid;
        place-items: center;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: var(--corner-full);
        background: color-mix(in srgb, var(--color-accent-fill) 12%, transparent);
        font-size: 1.375rem;
        line-height: 1;
    }
    .step-heading {
        display: grid;
        grid-template-columns: 4rem minmax(0, 1fr);
        gap: var(--space-2);
    }
    .step-heading :global(.step-emoji) {
        text-align: center;
    }
    /* Order and removal side by side, each as large as a field. */
    .step-actions {
        display: flex;
    }
    .step-actions :global(.btn) {
        width: 2.75rem;
        height: 2.75rem;
    }
    .background-remove {
        display: grid;
        place-items: center;
        height: 2.75rem;
    }
    /* As large as the field beside it, so the hover background has room around the icon. */
    .background-remove :global(.btn) {
        width: 2.75rem;
        height: 2.75rem;
    }
    .background-fields {
        display: grid;
        gap: var(--space-2);
        min-width: 0;
    }
    /* A background is a picture, not a mark: it fills its thumbnail. */
    .preview.media {
        padding: 0;
        overflow: hidden;
    }
    .preview.media > img,
    .preview.media > video {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    /* A fixed size, not a percentage: inside the grid a percentage height resolves
       against the image's own size and lets a large icon grow out of the box. */
    .preview > img {
        display: block;
        width: 3rem;
        height: 3rem;
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
        .settings-list > li {
            grid-template-columns: minmax(0, 1fr);
        }
        .backgrounds > li {
            grid-template-columns: auto minmax(0, 1fr);
        }
        .setting-actions {
            grid-column: 1;
        }
    }
</style>
