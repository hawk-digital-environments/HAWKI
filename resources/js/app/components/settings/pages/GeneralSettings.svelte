<!--
  @component General settings: interface language (persisted server-side),
  theme, and the danger zone (delete all data).
-->
<script lang="ts">
    import z from 'zod';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import UnfoldMoreIcon from '$lib/components/ui/icons/iconset/UnfoldMoreIcon.svelte';
    import SettingsPage from '$lib/app/components/settings/SettingsPage.svelte';
    import SettingsGroup from '$lib/app/components/settings/SettingsGroup.svelte';
    import SettingsRow from '$lib/app/components/settings/SettingsRow.svelte';
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

    const uid = $props.id();
    const localeValueId = `${uid}-locale`;

    const localeItems = config.locale.available.map((locale) => ({
        value: locale.lang,
        label: locale.nameInLanguage
    }));

    let localeValue = $state(app.localization.locale.lang);
    let localeSaving = $state(false);
    const localeLabel = $derived(localeItems.find((item) => item.value === localeValue)?.label ?? localeValue);

    async function changeLocale(lang: string): Promise<void> {
        if (!lang || lang === app.localization.locale.lang) return;

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
        {key: 'light', label: __('ui.settings.general.themeLight')},
        {key: 'dark', label: __('ui.settings.general.themeDark')}
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

<SettingsPage title={__('ui.settings.general.title')}>
    <SettingsGroup>
        <SettingsRow
            label={__('ui.settings.general.languageLabel')}
            description={__('ui.settings.general.languageHint')}
        >
            {#snippet control({labelId})}
                <DropdownMenu title={__('ui.settings.general.languageLabel')} align="end" disabled={localeSaving}>
                    {#snippet trigger({props})}
                        <Button
                            {...props}
                            variant="stroke"
                            size="xs"
                            iconRight={UnfoldMoreIcon}
                            disabled={localeSaving}
                            aria-labelledby="{labelId} {localeValueId}"
                        >
                            <span id={localeValueId}>{localeLabel}</span>
                        </Button>
                    {/snippet}

                    <DropdownMenuRadioGroup bind:value={localeValue} onValueChange={changeLocale}>
                        {#each localeItems as item (item.value)}
                            <DropdownMenuRadioItem value={item.value} indicator="check">
                                {item.label}
                            </DropdownMenuRadioItem>
                        {/each}
                    </DropdownMenuRadioGroup>
                </DropdownMenu>
            {/snippet}
        </SettingsRow>

        <SettingsRow
            label={__('ui.settings.general.themeLabel')}
            description={__('ui.settings.general.themeHint')}
        >
            {#snippet control()}
                <div class="theme-switch">
                    <Tabs
                        items={themeItems}
                        value={themeStore.theme}
                        onChange={(key) => (themeStore.theme = key as AppTheme)}
                        aria-label={__('ui.settings.general.themeLabel')}
                    />
                </div>
            {/snippet}
        </SettingsRow>
    </SettingsGroup>

    <SettingsGroup>
        <SettingsRow
            tone="danger"
            label={__('ui.settings.general.deleteData')}
            description={__('ui.settings.general.deleteDataHint')}
        >
            {#snippet control()}
                <Button size="xs" variant="delete" onclick={() => (confirmDeleteOpen = true)}>
                    {__('ui.settings.general.deleteDataButton')}
                </Button>
            {/snippet}
        </SettingsRow>
    </SettingsGroup>
</SettingsPage>

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
    /* A definite width lets the two segments split it evenly. */
    .theme-switch {
        width: 10rem;
    }
</style>
