<!--
  @component Client-side experiments for the new UI. Renders one toggle row per
  entry in the `experiments` store's registry — the whole row is the switch;
  the empty-state alert only shows when no experiments are registered. Flags
  are persisted in localStorage.
-->
<script lang="ts">
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
    import SettingsPage from '$lib/app/components/settings/SettingsPage.svelte';
    import SettingsGroup from '$lib/app/components/settings/SettingsGroup.svelte';
    import SettingsRow from '$lib/app/components/settings/SettingsRow.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();

    const experiments = useStore('experiments');
    const {__} = useTranslator();
</script>

<SettingsPage title={__('ui.settings.experiments.title')}>
    {#if experiments.list.length === 0}
        <Alert
            size="small"
            icon={FlaskConicalIcon}
            title={__('ui.settings.experiments.emptyTitle')}
            description={__('ui.settings.experiments.emptyHint')}
        />
    {:else}
        <SettingsGroup>
            {#each experiments.list as experiment (experiment.id)}
                {@const enabled = experiments.isEnabled(experiment.id)}
                <SettingsRow
                    label={__(experiment.titleKey)}
                    description={__(experiment.descriptionKey)}
                    role="switch"
                    aria-checked={enabled}
                    onclick={() => experiments.setEnabled(experiment.id, !enabled)}
                >
                    {#snippet control()}
                        <Switch checked={enabled} presentational/>
                    {/snippet}
                </SettingsRow>
            {/each}
        </SettingsGroup>
    {/if}
</SettingsPage>
