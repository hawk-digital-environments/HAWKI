<!--
  @component Client-side experiments for the new UI. There are no experiments
  yet — the category exists so upcoming feature toggles have a home.
-->
<script lang="ts">
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteComponentProps} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

    const {}: RouteComponentProps = $props();

    const {__} = useTranslator();
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.settings.experiments.title')}</h2>
        <p>{__('ui.settings.experiments.description')}</p>
    </header>

    <Alert
        size="small"
        icon={FlaskConicalIcon}
        title={__('ui.settings.experiments.emptyTitle')}
        description={__('ui.settings.experiments.emptyHint')}
    />
</section>

<style>
    .settings-section,
    header {
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

    p {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
</style>
