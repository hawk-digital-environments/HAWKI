<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteComponentProps} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

    const {}: RouteComponentProps = $props();

    const {__} = useTranslator();
    let language = $state('auto');
    let emailNotifications = $state(true);
    let productUpdates = $state(false);
    let saved = $state(false);

    function markDirty(): void {
        saved = false;
    }
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.profile.preferenceSettings.title')}</h2>
        <p>{__('ui.profile.preferenceSettings.description')}</p>
    </header>

    <label class="select-field">
        <span>{__('ui.profile.preferenceSettings.languageLabel')}</span>
        <select bind:value={language} onchange={markDirty}>
            <option value="auto">{__('ui.profile.preferenceSettings.languageAuto')}</option>
            <option value="de">Deutsch</option>
            <option value="en">English</option>
        </select>
    </label>

    <div class="setting-list">
        <button type="button" class="setting-row" onclick={() => { emailNotifications = !emailNotifications; markDirty(); }}>
            <span>
                <strong>{__('ui.profile.preferenceSettings.emailNotifications')}</strong>
                <small>{__('ui.profile.preferenceSettings.emailNotificationsHint')}</small>
            </span>
            <Switch checked={emailNotifications} presentational/>
        </button>
        <button type="button" class="setting-row" onclick={() => { productUpdates = !productUpdates; markDirty(); }}>
            <span>
                <strong>{__('ui.profile.preferenceSettings.productUpdates')}</strong>
                <small>{__('ui.profile.preferenceSettings.productUpdatesHint')}</small>
            </span>
            <Switch checked={productUpdates} presentational/>
        </button>
    </div>

    <div class="form-footer">
        <span class:saved>{saved ? __('ui.profile.mockSaved') : __('ui.profile.mockNotice')}</span>
        <Button size="sm" onclick={() => (saved = true)}>{__('ui.profile.saveChanges')}</Button>
    </div>
</section>

<style>
    .settings-section,
    header,
    .select-field,
    .setting-row > span {
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
    p {
        margin: 0;
    }

    h2 {
        font-size: var(--font-size-md);
    }

    p,
    small,
    .form-footer > span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .select-field {
        gap: var(--space-1_5);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    select {
        min-height: 2.5rem;
        padding: 0 var(--space-3);
        border: var(--border);
        border-radius: var(--corner-sm);
        background: var(--color-surface-light);
        color: var(--color-text);
        font: inherit;
        font-weight: var(--font-weight-normal);
    }

    .setting-list {
        overflow: hidden;
        border: var(--border);
        border-radius: var(--corner-md);
    }

    .setting-row {
        display: flex;
        width: 100%;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-3);
        border: 0;
        border-bottom: var(--divider);
        background: transparent;
        color: var(--color-text);
        font: inherit;
        text-align: left;
        cursor: pointer;
    }

    .setting-row:last-child {
        border-bottom: 0;
    }

    .setting-row:hover {
        background: var(--color-hover);
    }

    .setting-row > span {
        min-width: 0;
        flex: 1;
        gap: var(--space-0_5);
    }

    .setting-row strong {
        font-size: var(--font-size-xs);
    }

    .form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
    }

    .form-footer > span.saved {
        color: var(--color-success);
    }
</style>
