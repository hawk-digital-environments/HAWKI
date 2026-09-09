<!--
  @component Language and theme controls for the authentication pages.

  Guests have no settings dialog yet, so the two preferences that matter before
  signing in live here. The language is persisted the same way as in the general
  settings (server session/cookie, plus the account once signed in); the theme is
  stored by the theme store.
-->
<script lang="ts">
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Sun01Icon from '$lib/components/ui/icons/iconset/Sun01Icon.svelte';
    import Moon01Icon from '$lib/components/ui/icons/iconset/Moon01Icon.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useConfig } from '$lib/app/hooks/useConfig.svelte.js';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

    const app = useApp();
    const config = useConfig();
    const theme = useStore('theme');
    const { __ } = useTranslator();

    const localeItems = config.locale.available.map((locale) => ({
        value: locale.lang,
        label: locale.nameInLanguage
    }));

    let localeValue = $state(app.localization.locale.lang);
    let saving = $state(false);
    let error = $state('');

    async function changeLocale(lang: string): Promise<void> {
        // Guarded instead of disabling the select: a disabled, focused trigger drops keyboard focus to <body>.
        if (saving || !lang || lang === app.localization.locale.lang) return;
        saving = true;
        error = '';
        try {
            await app.restApi.postToResourceAction('users', 'actions/locale', { locale: lang });
            await app.localization.setLocale(lang);
            // Keep subsequent API requests sending the new locale header.
            app.connection.locale = lang;
        } catch (e) {
            console.error('Failed to change the locale', e);
            localeValue = app.localization.locale.lang;
            error = __('ui.settings.general.languageError');
        } finally {
            saving = false;
        }
    }

    const themeLabel = $derived(
        theme.isDark ? __('ui.auth.preferences.switchToLight') : __('ui.auth.preferences.switchToDark')
    );
</script>

<div class="prefs">
    <SingleSelect
        bind:value={localeValue}
        items={localeItems}
        onValueChange={changeLocale}
        triggerProps={{ 'aria-label': __('ui.settings.general.languageLabel'), 'aria-busy': saving }}
    />
    <Button
        variant="ghost"
        size="sm"
        iconLeft={theme.isDark ? Sun01Icon : Moon01Icon}
        aria-label={themeLabel}
        title={themeLabel}
        onclick={() => { theme.theme = theme.isDark ? 'light' : 'dark'; }}
    />
</div>
{#if error}<p class="auth-error" role="alert">{error}</p>{/if}

<style>
    .prefs {
        display: flex;
        align-items: center;
        gap: var(--space-1);
    }
</style>
