<!--
  @component Shows why an assistant cannot run right now (see `assistantUnavailability`):
  a status pill, or with `withHint` a notice that also explains it. Renders nothing while the
  assistant can run.

  ## Usage
  ```svelte
  <AssistantAvailabilityStatus {assistant}/>
  <AssistantAvailabilityStatus {assistant} withHint/>
  ```
-->
<script lang="ts">
    import type {Assistant} from '$plugins/assistants/types/assistant';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import CancelCircleIcon from '$lib/components/ui/icons/iconset/CancelCircleIcon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {assistantUnavailability} from '$plugins/assistants/utils/assistantAvailability';

    interface Props {
        assistant: Assistant;
        /** Also explain what it means and, for someone who can edit it, how to fix it. */
        withHint?: boolean;
    }

    const {assistant, withHint = false}: Props = $props();
    const {__} = useTranslator();
    const models = useStore('ai-models');

    const unavailability = $derived(assistantUnavailability(assistant, models));
    const canEdit = $derived(assistant.actionPermissions?.update === true);
</script>

{#if unavailability === 'deactivated'}
    {#if withHint}
        <Alert
            size="small"
            tintText
            tone="error"
            icon={CancelCircleIcon}
            title={__('assistants.availability.deactivated')}
            description={__(canEdit ? 'assistants.availability.deactivatedHintEditor' : 'assistants.availability.deactivatedHint')}
        />
    {:else}
        <StatusPill label={__('assistants.availability.deactivated')} icon={CancelCircleIcon} tone="error"/>
    {/if}
{:else if unavailability === 'offline'}
    {#if withHint}
        <Alert
            size="small"
            tintText
            tone="warning"
            icon={AlertCircleIcon}
            title={__('assistants.availability.offline')}
            description={__('assistants.availability.offlineHint')}
        />
    {:else}
        <StatusPill label={__('assistants.availability.offline')} icon={AlertCircleIcon} tone="warning"/>
    {/if}
{/if}
