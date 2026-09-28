<!--
  @component Regenerate action for an assistant message: a dropdown ("model
  picker lite") with a quick action that re-runs the reply with the model that
  produced it, followed by one submenu per provider from the `ai-models` store
  listing that provider's models (the original one in bold). Picking any row
  calls `onRegenerate(message, model)` right away; the page owning the
  conversation runs the request through `ChatTransport.regenerateMessage`, so
  the composer is not involved at all. `model` is `null` for the quick action
  when the original model is no longer known (the transport then falls back).
  Offline models are disabled. Below the `md` breakpoint the menu renders as a
  bottom sheet (handled by `DropdownMenu`).

  Picking a model hides the message's action bar (and with it this trigger)
  while the reply streams, so the menu would hand focus back to a removed
  element. Pass `returnFocusTo` (the message element) to move focus there instead.

  An author that fixes its model (an assistant with `modelSelect: false`
  capabilities, contributed via the `aiAssistants` hook) reduces the menu to
  the single quick action: the run can only ever use that assistant's model
  — the backend enforces it (`AssistantChatAgentFactory::resolveModelId`)
  — so offering other picks would lie.

  ## Usage
  Rendered in `ChatMessage.svelte`'s action bar for assistant messages when the
  page provides an `onRegenerate` handler:
  ```svelte
  <RegenerateMenu {message} onRegenerate={(message, model) => regenerate(message, model)}/>
  ```
-->
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import DropdownMenuSub from '$lib/components/ui/dropdown-menu/DropdownMenuSub.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import type {ChatMessage} from '$plugins/core/modules/chat/types.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** The assistant message to regenerate. */
        message: ChatMessage;
        /** Called with the picked model; `null` means "the model that produced the message". */
        onRegenerate: (message: ChatMessage, model: AiModel | null) => void;
        /** Receives focus when the menu closes after a regeneration was picked. */
        returnFocusTo?: HTMLElement | null;
    }

    const {message, onRegenerate, returnFocusTo = null}: Props = $props();
    const {__} = useTranslator();
    const aiModelStore = useStore('ai-models');
    const handleStore = useStore('ai-handle');

    let open = $state(false);

    // Provider groups in store order; models without a provider share the "other" group.
    const groups = $derived(Map.groupBy(aiModelStore.models, model => model.provider?.name ?? __('chat.composer.modelPicker.otherProvider')));

    // The model that produced the message, if it is still known; `null` for legacy messages
    // without a model or when the model has been removed since. The quick action then
    // hands `null` on and lets the transport pick the fallback.
    const usedModel = $derived(message.model ? aiModelStore.getOneById(message.model) : null);

    // The participant that authored the message, from its persisted identity
    // (`message.assistant.handle`) — the same source `chatSend`'s regen
    // pinning uses. Null for plain model messages, or when the assistant
    // was deleted since (the menu then stays unrestricted).
    const author = $derived.by(() => {
        const handle = message.assistant?.handle;
        return handle
            ? handleStore.assistants.find(entry => entry.chatBinding === handle) ?? null
            : null;
    });

    // An author that fixes its model (see `AiAssistant.capabilities`): the
    // only legal pick is the quick action's — the assistant's model.
    const modelFixed = $derived(author?.capabilities?.modelSelect === false);

    let regenerated = false;

    function regenerate(model: AiModel | null): void {
        regenerated = true;
        onRegenerate(message, model);
    }

    function handleCloseAutoFocus(event: Event): void {
        if (!regenerated) return;
        regenerated = false;
        if (!returnFocusTo) return;
        event.preventDefault();
        returnFocusTo.focus({preventScroll: true});
    }
</script>

<DropdownMenu bind:open align="start" contentProps={{onCloseAutoFocus: handleCloseAutoFocus}}>
    {#snippet trigger({props})}
        <ButtonWithTooltip
            {...props}
            variant="iconGhost"
            size="xs"
            iconLeft={ArrowReloadHorizontalIcon}
            tooltip={__('chat.actions.regenerate')}
            highlight={props['data-state']}
        />
    {/snippet}
    <!-- Quick action: same model as before (or the transport's fallback when it is gone). -->
    <DropdownMenuItem class="regen-menu-item" iconLeft={ArrowReloadHorizontalIcon} disabled={usedModel?.status === 'offline'} onSelect={() => regenerate(usedModel)}>
        <span class="regen-menu-label">
            {usedModel ? usedModel.label : __('chat.actions.regenerate')}
        </span>
    </DropdownMenuItem>
    {#if !modelFixed}
        <DropdownMenuSeparator/>
        {#each groups.entries() as [provider, models] (provider)}
            <DropdownMenuSub label={provider}>
                {#each models as model (model.model_id)}
                    {@const offline = model.status === 'offline'}
                    {@const used = model.model_id === usedModel?.model_id}
                    <DropdownMenuItem class="regen-menu-item" disabled={offline} onSelect={() => regenerate(model)}>
                        <span class="regen-menu-label" class:regen-menu-label--used={used}>{model.label}</span>
                    </DropdownMenuItem>
                {/each}
            </DropdownMenuSub>
        {/each}
    {/if}
</DropdownMenu>

<style>
    :global(.dropdown-item.regen-menu-item) {
        gap: var(--space-3);
    }

    .regen-menu-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .regen-menu-label--used {
        font-weight: var(--font-weight-medium, 500);
    }
</style>
