<!--
  @component Shows why an assistant cannot run right now (see `assistantUnavailability`):
  a status pill, plus an optional explanation. Renders nothing while the assistant can run.

  ## Usage
  ```svelte
  <AssistantAvailabilityStatus {assistant}/>
  <AssistantAvailabilityStatus {assistant} withHint/>
  ```
-->
<script lang="ts">
    import type {Assistant} from '$plugins/assistants/types/assistant';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
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
    <StatusPill label={__('assistants.availability.deactivated')} icon={CancelCircleIcon} tone="error"/>
    {#if withHint}
        <p class="availability-hint">
            {__(canEdit ? 'assistants.availability.deactivatedHintEditor' : 'assistants.availability.deactivatedHint')}
        </p>
    {/if}
{:else if unavailability === 'offline'}
    <StatusPill label={__('assistants.availability.offline')} icon={AlertCircleIcon} tone="warning"/>
    {#if withHint}
        <p class="availability-hint">{__('assistants.availability.offlineHint')}</p>
    {/if}
{/if}

<style>
    .availability-hint {
        /* In a wrapping badge row the explanation takes its own line under the pill. */
        flex-basis: 100%;
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
