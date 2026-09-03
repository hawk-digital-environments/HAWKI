<!--
  @component General settings: interface language (persisted server-side),
  theme, and the danger area (delete all data).
-->
<script lang="ts">
    import z from 'zod';
    import Button from '$lib/components/ui/button/Button.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useRestApi} from '$lib/app/hooks/useApi.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import type {AppTheme} from '$plugins/core/stores/ThemeStore.svelte.js';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();

    const app = useApp();
    const config = useConfig();
    const restApi = useRestApi();
    const themeStore = useStore('theme');
    const keychainStore = useStore('keychain');
    const toast = useToastContext();
    const {__} = useTranslator();

    const localeItems = config.locale.available.map((locale) => ({
        value: locale.lang,
        label: locale.nameInLanguage
    }));

    let localeValue = $state(app.localization.locale.lang);
    let localeSaving = $state(false);

    async function changeLocale(lang: string): Promise<void> {
        // Guarded here instead of disabling the select: disabling the focused
        // trigger mid-request would drop keyboard focus to <body>.
        if (localeSaving || !lang || lang === app.localization.locale.lang) return;

        localeSaving = true;
        try {
            await restApi.postToResourceAction('users', 'actions/locale', {locale: lang});
            await app.localization.setLocale(lang);
            // Keep subsequent API requests sending the new locale header.
            app.connection.locale = lang;
        } catch (error) {
            console.error('Failed to change the locale', error);
            localeValue = app.localization.locale.lang;
            toast.error(__('ui.settings.general.languageError'));
        } finally {
            localeSaving = false;
        }
    }

    // $derived so the labels follow runtime locale switches.
    const themeItems = $derived([
        {value: 'light', label: __('ui.settings.general.themeLight')},
        {value: 'dark', label: __('ui.settings.general.themeDark')}
    ]);

    let confirmDeleteOpen = $state(false);
    let deleting = $state(false);

    async function deleteAllData(): Promise<void> {
        deleting = true;
        try {
            const response = await restApi.postToResourceAction('users', 'actions/reset-profile', {}, {
                schema: z.object({redirectUri: z.string()})
            });
            keychainStore.clearLocalSession();
            window.location.href = response.redirectUri;
        } catch (error) {
            console.error('Failed to reset the profile', error);
            toast.error(__('ui.settings.general.deleteError'));
            deleting = false;
            confirmDeleteOpen = false;
        }
    }
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.settings.general.title')}</h2>
        <p>{__('ui.settings.general.description')}</p>
    </header>

    <div class="field">
        <span class="field-label" id="settings-language-label">{__('ui.settings.general.languageLabel')}</span>
        <SingleSelect
            bind:value={localeValue}
            items={localeItems}
            onValueChange={changeLocale}
            triggerProps={{'aria-labelledby': 'settings-language-label', 'aria-busy': localeSaving}}
        />
    </div>

    <div class="field">
        <span class="field-label" id="settings-theme-label">{__('ui.settings.general.themeLabel')}</span>
        <SingleSelect
            bind:value={
                () => themeStore.theme,
                (value) => (themeStore.theme = value as AppTheme)
            }
            items={themeItems}
            triggerProps={{'aria-labelledby': 'settings-theme-label'}}
        />
    </div>

    <div class="danger-area">
        <h3>{__('ui.settings.general.dangerTitle')}</h3>

        <div class="danger-row">
            <span>
                <strong>{__('ui.settings.general.deleteData')}</strong>
                <small>{__('ui.settings.general.deleteDataHint')}</small>
            </span>
            <Button size="sm" variant="delete" onclick={() => (confirmDeleteOpen = true)}>
                {__('ui.settings.general.deleteDataButton')}
            </Button>
        </div>
    </div>
</section>

<ConfirmDialog
    bind:open={confirmDeleteOpen}
    title={__('ui.settings.general.deleteConfirmTitle')}
    description={__('ui.settings.general.deleteConfirmText')}
    okLabel={__('ui.settings.general.deleteConfirmButton')}
    cancelLabel={__('ui.settings.common.cancel')}
    confirmVariant="delete"
    busy={deleting}
    onConfirm={deleteAllData}
/>

<style>
    .settings-section,
    header,
    .field,
    .danger-row > span {
        display: flex;
        flex-direction: column;
    }

    .settings-section {
        gap: var(--space-5);
        max-width: 28rem;
    }

    header {
        gap: var(--space-1);
    }

    h2,
    h3,
    p {
        margin: 0;
    }

    h2 {
        font-size: var(--font-size-md);
    }

    p,
    small {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .field {
        gap: var(--space-1_5);
    }

    .field-label {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    .danger-area {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        padding: var(--space-3);
        border: 1px solid color-mix(in oklch, var(--color-error) 45%, transparent);
        border-radius: var(--corner-md);
    }

    h3 {
        color: var(--color-error);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .danger-row {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .danger-row > span {
        min-width: 0;
        flex: 1;
        gap: var(--space-0_5);
    }

    .danger-row strong {
        font-size: var(--font-size-xs);
    }
</style>
