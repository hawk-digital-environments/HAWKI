<!--
  @component Client-side experiments for the new UI. Renders one toggle row per
  entry in the `experiments` store's registry; the empty-state alert only shows
  when no experiments are registered. Flags are persisted in localStorage.
-->
<script lang="ts">
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteComponentProps} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

    const {}: RouteComponentProps = $props();

    const experiments = useStore('experiments');
    const {__} = useTranslator();
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.settings.experiments.title')}</h2>
        <p>{__('ui.settings.experiments.description')}</p>
    </header>

    {#if experiments.list.length === 0}
        <Alert
            size="small"
            icon={FlaskConicalIcon}
            title={__('ui.settings.experiments.emptyTitle')}
            description={__('ui.settings.experiments.emptyHint')}
        />
    {:else}
        <ul class="experiment-list">
            {#each experiments.list as experiment (experiment.id)}
                <li class="experiment-row">
                    <span>
                        <strong>{__(experiment.titleKey)}</strong>
                        <small>{__(experiment.descriptionKey)}</small>
                    </span>
                    <Switch
                        aria-label={__(experiment.titleKey)}
                        bind:checked={
                            () => experiments.isEnabled(experiment.id),
                            (value) => experiments.setEnabled(experiment.id, value)
                        }
                    />
                </li>
            {/each}
        </ul>
    {/if}
</section>

<style>
    .settings-section,
    header,
    .experiment-row > span {
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
    small {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .experiment-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .experiment-row {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .experiment-row > span {
        min-width: 0;
        flex: 1;
        gap: var(--space-0_5);
    }

    .experiment-row strong {
        font-size: var(--font-size-xs);
    }
</style>
