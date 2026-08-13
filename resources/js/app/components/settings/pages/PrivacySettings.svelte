<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteComponentProps} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

    const {}: RouteComponentProps = $props();

    const {__} = useTranslator();
    let saveHistory = $state(true);
    let personalizedSuggestions = $state(false);
    let saved = $state(false);

    function toggle(kind: 'history' | 'suggestions'): void {
        if (kind === 'history') saveHistory = !saveHistory;
        else personalizedSuggestions = !personalizedSuggestions;
        saved = false;
    }
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.profile.privacySettings.title')}</h2>
        <p>{__('ui.profile.privacySettings.description')}</p>
    </header>

    <div class="setting-list">
        <button type="button" class="setting-row" onclick={() => toggle('history')}>
            <span>
                <strong>{__('ui.profile.privacySettings.saveHistory')}</strong>
                <small>{__('ui.profile.privacySettings.saveHistoryHint')}</small>
            </span>
            <Switch checked={saveHistory} presentational/>
        </button>
        <button type="button" class="setting-row" onclick={() => toggle('suggestions')}>
            <span>
                <strong>{__('ui.profile.privacySettings.personalizedSuggestions')}</strong>
                <small>{__('ui.profile.privacySettings.personalizedSuggestionsHint')}</small>
            </span>
            <Switch checked={personalizedSuggestions} presentational/>
        </button>
    </div>

    <div class="privacy-note">
        <strong>{__('ui.profile.privacySettings.localOnlyTitle')}</strong>
        <span>{__('ui.profile.privacySettings.localOnlyHint')}</span>
    </div>

    <div class="form-footer">
        <span class:saved>{saved ? __('ui.profile.mockSaved') : __('ui.profile.mockNotice')}</span>
        <Button size="sm" onclick={() => (saved = true)}>{__('ui.profile.saveChanges')}</Button>
    </div>
</section>

<style>
    .settings-section,
    header,
    .setting-row > span,
    .privacy-note {
        display: flex;
        flex-direction: column;
    }

    .settings-section {
        gap: var(--space-5);
        max-width: 28rem;
    }

    header,
    .setting-row > span,
    .privacy-note {
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
    .privacy-note span,
    .form-footer > span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
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
    }

    .setting-row strong,
    .privacy-note strong {
        font-size: var(--font-size-xs);
    }

    .privacy-note {
        padding: var(--space-3);
        border-radius: var(--corner-md);
        background: var(--color-surface-light);
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
