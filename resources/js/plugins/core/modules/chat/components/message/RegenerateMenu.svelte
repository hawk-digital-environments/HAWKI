<!--
  @component Regenerate action for an assistant message: a dropdown with a
  quick action that re-runs the reply with the participant that produced it,
  followed by one submenu per "provider" of regeneration options. Core
  contributes the per-provider model groups (every pick is a participant:
  HAWKI, the default one, with that model); plugins add their own groups via
  the `chatRegenerateGroups` hook — the assistants plugin's "Assistants"
  group, for example. The page owning the conversation runs the pick through
  `ChatTransport.regenerateMessage`, which resolves the run via the `chatSend`
  hook — an assistant row re-runs the reply with that assistant's logic, a
  model row without it. Below the `md` breakpoint the menu renders as a
  bottom sheet (handled by `DropdownMenu`).

  Picking a row hides the message's action bar (and with it this trigger)
  while the reply streams, so the menu would hand focus back to a removed
  element. Pass `returnFocusTo` (the message element) to move focus there instead.

  ## Usage
  Rendered in `ChatMessage.svelte`'s action bar for assistant messages when the
  page provides an `onRegenerate` handler:
  ```svelte
  <RegenerateMenu {message} onRegenerate={(message, pick) => regenerate(message, pick)}/>
  ```
-->
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import DropdownMenuSub from '$lib/components/ui/dropdown-menu/DropdownMenuSub.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import AssistantRow from '$plugins/core/modules/chat/components/composer/AssistantRow.svelte';
    import {defaultAssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
    import type {ChatMessage} from '$plugins/core/modules/chat/types.js';
    import type {ChatRegenerateEntry, ChatRegenerateGroup, ChatRegeneratePick} from '$plugins/core/modules/chat/hooks/chatRegenerateHooks.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** The assistant message to regenerate. */
        message: ChatMessage;
        /** Called with the picked participant (and model, for HAWKI); see `ChatRegeneratePick`. */
        onRegenerate: (message: ChatMessage, pick: ChatRegeneratePick) => void;
        /** Receives focus when the menu closes after a regeneration was picked. */
        returnFocusTo?: HTMLElement | null;
    }

    const {message, onRegenerate, returnFocusTo = null}: Props = $props();
    const app = useApp();
    const {__} = useTranslator();
    const aiModelStore = useStore('ai-models');
    const handleStore = useStore('ai-handle');

    let open = $state(false);

    // The model that produced the message, if it is still known; `null` for legacy messages
    // without a model or when the model has been removed since. The quick action then
    // hands `null` on and lets the transport pick the fallback.
    const usedModel = $derived(message.model ? aiModelStore.getOneById(message.model) : null);

    // The default participant: the system assistant, identified by its API-configured
    // handle (`AiHandleStore.hawkiHandle`) like the composer menus do.
    const systemAssistant = $derived(handleStore.assistants.find(assistant => assistant.handle === handleStore.hawkiHandle) ?? null);

    // The participant that authored the message, from its persisted identity
    // (`message.assistant.handle`); null when it was deleted since.
    const author = $derived.by(() => {
        const handle = message.assistant?.handle;
        return handle
            ? handleStore.assistants.find(entry => entry.chatBinding === handle) ?? null
            : null;
    });

    // Quick action pick: the author, else HAWKI with the message's model.
    const quickPick = $derived.by((): ChatRegeneratePick | null => {
        if (author) return {participant: author};
        if (systemAssistant) return {participant: systemAssistant, model: usedModel?.model_id ?? null};
        return null;
    });

    // Core's own groups: one submenu per model provider (models without a provider share
    // the "other" group), each entry HAWKI with one model.
    const modelGroups = $derived.by((): ChatRegenerateGroup[] => {
        if (!systemAssistant) return [];
        return [...Map.groupBy(aiModelStore.models, model => model.provider?.name ?? __('chat.composer.modelPicker.otherProvider'))]
            .map(([label, models]) => ({
                id: `model:${label}`,
                label,
                entries: models.map(model => ({
                    id: model.model_id,
                    participant: systemAssistant,
                    model: model.model_id,
                    disabled: model.status === 'offline'
                }))
            }));
    });

    // Plugins extend the list with their own participant groups.
    const groups = $derived(app.hooks.apply('chatRegenerateGroups', modelGroups, {translate: __})
        .filter(group => group.entries.length > 0));

    function isUsed(entry: ChatRegenerateEntry): boolean {
        return author
            ? !entry.model && entry.participant.id === author.id
            : entry.model === usedModel?.model_id;
    }

    let regenerated = false;

    function regenerate(pick: ChatRegeneratePick): void {
        regenerated = true;
        onRegenerate(message, pick);
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
            size="sm"
            iconLeft={ArrowReloadHorizontalIcon}
            tooltip={__('chat.actions.regenerate')}
            highlight={props['data-state']}
        />
    {/snippet}
    <!-- Quick action: the author's assistant, or HAWKI with the message's model. -->
    <DropdownMenuItem
        class="regen-menu-item"
        iconLeft={ArrowReloadHorizontalIcon}
        disabled={!quickPick || (!author && usedModel?.status === 'offline')}
        onSelect={() => quickPick && regenerate(quickPick)}
    >
        <span class="regen-menu-label">
            {author ? author.label : usedModel ? usedModel.label : __('chat.actions.regenerate')}
        </span>
    </DropdownMenuItem>
    {#if groups.length > 0}
        <DropdownMenuSeparator/>
        {#each groups as group (group.id)}
            <DropdownMenuSub label={group.label}>
                {#each group.entries as entry (entry.id)}
                    {@const used = isUsed(entry)}
                    <DropdownMenuItem
                        class={['regen-menu-item', used && 'regen-menu-item--used'].filter(Boolean).join(' ')}
                        disabled={entry.disabled}
                        onSelect={() => regenerate(entry)}
                    >
                        {#if entry.model}
                            <span class="regen-menu-label">{aiModelStore.getOneById(entry.model)?.label ?? entry.model}</span>
                        {:else}
                            {@const appearance = entry.participant.appearance ?? defaultAssistantAppearance(entry.participant.label)}
                            <AssistantRow assistant={entry.participant} emoji={appearance.icon} colors={appearance.colors}/>
                        {/if}
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

    /* The used row reads like the quick action's pick. */
    :global(.dropdown-item.regen-menu-item--used) .regen-menu-label,
    :global(.dropdown-item.regen-menu-item--used .assistant-row-label) {
        font-weight: var(--font-weight-medium, 500);
    }
</style>
