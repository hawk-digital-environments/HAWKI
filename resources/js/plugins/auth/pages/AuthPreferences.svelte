<!--
  @component Language and theme controls for the authentication pages.

  Guests have no settings dialog yet, so the two preferences that matter before
  signing in live here. The language is persisted the same way as in the general
  settings (server session/cookie, plus the account once signed in); the theme is
  stored by the theme store.
-->
<script lang="ts">
    import {useLocaleSwitch} from '$lib/app/hooks/useLocaleSwitch.svelte.js';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Sun01Icon from '$lib/components/ui/icons/iconset/Sun01Icon.svelte';
    import Moon01Icon from '$lib/components/ui/icons/iconset/Moon01Icon.svelte';
    import { useConfig } from '$lib/app/hooks/useConfig.svelte.js';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

    const config = useConfig();
    const theme = useStore('theme');
    const { __ } = useTranslator();

    const localeItems = config.locale.available.map((locale) => ({
        value: locale.lang,
        label: locale.nameInLanguage
    }));

    let error = $state('');
    const locale = useLocaleSwitch(() => { error = __('ui.settings.general.languageError'); });

    const themeLabel = $derived(
        theme.isDark ? __('ui.auth.preferences.switchToLight') : __('ui.auth.preferences.switchToDark')
    );
</script>

<div class="prefs">
    <SingleSelect
        bind:value={locale.value}
        items={localeItems}
        onValueChange={(lang: string) => { error = ''; return locale.change(lang); }}
        triggerProps={{ 'aria-label': __('ui.settings.general.languageLabel'), 'aria-busy': locale.saving }}
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
