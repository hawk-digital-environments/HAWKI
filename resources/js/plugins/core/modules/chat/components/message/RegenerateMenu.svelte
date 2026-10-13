<!--
  @component Regenerate action for an assistant message: a dropdown ("model
  picker lite") offering two kinds of targets:

  - **Assistants** — one row per taggable assistant (from the `ai-handle`
    store, contributed via the `aiAssistants` hook). Re-runs the reply through
    that assistant, including its own logic and configuration.
  - **Models only** — one submenu per provider from the `ai-models` store.
    Re-runs the reply with that model directly, without any assistant logic.

  The quick action on top repeats whatever produced the message: its assistant
  when it was authored by one that still exists, else its model. The original
  pick is shown in bold in its list. Picking any row calls
  `onRegenerate(message, target)` right away; the page owning the conversation
  runs the request through `ChatTransport.regenerateMessage`, so the composer is
  not involved at all. A model target of `null` (quick action when the original
  model is no longer known) lets the transport fall back. Offline models are
  disabled. Below the `md` breakpoint the menu renders as a bottom sheet
  (handled by `DropdownMenu`).

  Picking a target hides the message's action bar (and with it this trigger)
  while the reply streams, so the menu would hand focus back to a removed
  element. Pass `returnFocusTo` (the message element) to move focus there instead.

  ## Usage
  Rendered in `ChatMessage.svelte`'s action bar for assistant messages when the
  page provides an `onRegenerate` handler:
  ```svelte
  <RegenerateMenu {message} onRegenerate={(message, target) => regenerate(message, target)}/>
  ```
-->
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import DropdownMenuSub from '$lib/components/ui/dropdown-menu/DropdownMenuSub.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import type {ChatMessage} from '$plugins/core/modules/chat/types.js';
    import type {RegenerateTarget} from '$plugins/core/modules/chat/transport/ChatTransport.js';
    import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** The assistant message to regenerate. */
        message: ChatMessage;
        /** Called with the picked target: an assistant (by bare handle) or a plain model
         *  (`modelId: null` means "the model that produced the message"). */
        onRegenerate: (message: ChatMessage, target: RegenerateTarget) => void;
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

    // Only assistant-backed entries can run a reply (HAWKI itself has no binding — a plain
    // model run is what the model list is for).
    const assistants = $derived(
        handleStore.assistants.filter((entry): entry is AiAssistant & {chatBinding: string} => !!entry.chatBinding)
    );

    // The model that produced the message, if it is still known; `null` for legacy messages
    // without a model or when the model has been removed since. The quick action then
    // hands `null` on and lets the transport pick the fallback.
    const usedModel = $derived(message.model ? aiModelStore.getOneById(message.model) : null);

    // The assistant that authored the message, from its persisted identity
    // (`message.assistant.handle`). Null for plain model messages, or when the
    // assistant was deleted since (the quick action then reruns the model).
    const author = $derived.by(() => {
        const handle = message.assistant?.handle;
        return handle ? assistants.find(entry => entry.chatBinding === handle) ?? null : null;
    });

    let regenerated = false;

    function regenerate(target: RegenerateTarget): void {
        regenerated = true;
        onRegenerate(message, target);
    }

    function regenerateSame(): void {
        regenerate(author
            ? {kind: 'assistant', handle: author.chatBinding}
            : {kind: 'model', modelId: usedModel?.model_id ?? null});
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
    <!-- Quick action: same assistant / model as before (or the transport's fallback when it is gone). -->
    <DropdownMenuItem class="regen-menu-item" iconLeft={ArrowReloadHorizontalIcon} disabled={!author && usedModel?.status === 'offline'} onSelect={regenerateSame}>
        <span class="regen-menu-label">
            {author ? author.label : usedModel ? usedModel.label : __('chat.actions.regenerate')}
        </span>
    </DropdownMenuItem>
    <DropdownMenuSeparator/>
    {#if assistants.length > 0}
        <DropdownMenuSub label={__('chat.regenerate.assistantsGroup')}>
            {#each assistants as assistant (assistant.id)}
                <DropdownMenuItem class="regen-menu-item" onSelect={() => regenerate({kind: 'assistant', handle: assistant.chatBinding})}>
                    <span class="regen-menu-label" class:regen-menu-label--used={assistant.id === author?.id}>{assistant.label}</span>
                </DropdownMenuItem>
            {/each}
        </DropdownMenuSub>
        <DropdownMenuLabel>{__('chat.regenerate.modelsGroup')}</DropdownMenuLabel>
    {/if}
    {#each groups.entries() as [provider, models] (provider)}
        <DropdownMenuSub label={provider}>
            {#each models as model (model.model_id)}
                {@const offline = model.status === 'offline'}
                {@const used = !author && model.model_id === usedModel?.model_id}
                <DropdownMenuItem class="regen-menu-item" disabled={offline} onSelect={() => regenerate({kind: 'model', modelId: model.model_id})}>
                    <span class="regen-menu-label" class:regen-menu-label--used={used}>{model.label}</span>
                </DropdownMenuItem>
            {/each}
        </DropdownMenuSub>
    {/each}
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
