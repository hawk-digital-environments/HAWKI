<!--
  @component Detail panel for a single assistant, shown in place of the assistant list inside
  `AssistantMenu`'s `DropdownMenuDetailView` when the user clicks a row's info icon — the
  `@` menu's counterpart to `ToolMenuDetail`.

  Shows the assistant's emoji swatch, display name and `@handle`, a `MenuPinButton` and a
  toggle (the latter mirrors the checkbox in `AssistantMenuListItem`, wired to the same
  `entry.onToggle`), and the assistant's description in full.

  Owns its own keyboard handling (`onDetailKeydown`) for the same reason `ToolMenuDetail`
  does: the panel lives inside a bits-ui dropdown menu whose roving-tabindex model doesn't
  fit a sub-panel, so Escape/ArrowLeft close the detail (via `onCloseDetail`) and
  ArrowUp/ArrowDown/Tab move focus between this panel's own focusable children.

  ## Usage
  Rendered by `AssistantMenu` inside the `details` snippet of `DropdownMenuDetailView`,
  driven by which assistant's info icon was last clicked (`detailAssistantId`):
  ```svelte
  <DropdownMenuDetailView open={!!detailEntry}>
      {#snippet details()}
          {#if detailEntry}
              <AssistantMenuDetail entry={detailEntry} onCloseDetail={closeAssistantDetail}/>
          {/if}
      {/snippet}
      <AssistantMenuList .../>
  </DropdownMenuDetailView>
  ```
-->
<script lang="ts">
    import {onMount} from 'svelte';
    import type {AssistantMenuEntry} from '$plugins/core/modules/chat/components/composer/AssistantMenuListItem.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();

    interface Props {
        /** The assistant to show details for. Passed as a fresh copy (`{...entry}`) by
         *  `AssistantMenu` so `active` re-renders while the detail view is open. */
        entry: AssistantMenuEntry;
        /** Called when the user backs out of the detail view (Back button, Escape, or ArrowLeft).
         *  `AssistantMenu` clears `detailAssistantId` and returns focus to the row that opened it. */
        onCloseDetail: () => void;
    }

    let {entry, onCloseDetail}: Props = $props();

    // Same pin semantics as the list row: a local composer pin or the row's
    // server-side flag (a favourite), with the toggle routed accordingly.
    const pinned = $derived(
        pinStore.isPinned('assistant', entry.assistant.id) || !!entry.assistant.pinned
    );

    function togglePin() {
        if (entry.assistant.onTogglePin) {
            entry.assistant.onTogglePin(!pinned);
        } else {
            pinStore.toggle('assistant', entry.assistant.id);
        }
    }

    let detailEl = $state<HTMLDivElement | null>(null);
    let backEl = $state<HTMLButtonElement | null>(null);
    let toggleEl = $state<HTMLButtonElement | null>(null);

    // Move focus into the panel when it opens, landing on the primary control.
    onMount(() => {
        const target = toggleEl ?? backEl;
        const raf = requestAnimationFrame(() => target?.focus());
        return () => cancelAnimationFrame(raf);
    });

    function focusables(): HTMLElement[] {
        if (!detailEl) return [];
        return Array.from(detailEl.querySelectorAll<HTMLElement>('button:not([disabled]), [tabindex="0"]'));
    }

    function moveFocus(direction: 1 | -1) {
        const items = focusables();
        if (items.length === 0) return;
        const current = items.indexOf(document.activeElement as HTMLElement);
        const next = (current + direction + items.length) % items.length;
        items[next].focus();
    }

    // The panel lives inside a bits-ui menu whose keyboard model (arrow roving,
    // Tab-to-close, Escape-to-close) doesn't fit a sub-panel. Stop the handled
    // keys from reaching it and drive focus/back navigation ourselves.
    function onDetailKeydown(event: KeyboardEvent) {
        switch (event.key) {
            case 'Escape':
            case 'ArrowLeft':
                event.preventDefault();
                event.stopPropagation();
                onCloseDetail?.();
                break;
            case 'ArrowDown':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(-1);
                break;
            case 'Tab':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(event.shiftKey ? -1 : 1);
                break;
        }
    }
</script>
<!--
  Container-level keydown only delegates focus/back navigation to the child
  buttons (Back, toggle), which carry their own roles. It is not an interactive
  widget itself.
-->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    class="assistant-detail"
    style:--assistant-from={entry.colors.from}
    style:--assistant-to={entry.colors.to}
    bind:this={detailEl}
    onkeydown={onDetailKeydown}>
    <button
        type="button"
        class="assistant-detail-back"
        bind:this={backEl}
        onpointerdowncapture={(e) => e.stopPropagation()}
        onclick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onCloseDetail?.();
        }}>
        <ArrowLeft01Icon size={16}/>
        <span>{__('chat.composer.assistantMenu.backButton')}</span>
    </button>

    <div class="assistant-detail-header">
        <span class="assistant-detail-title">
            <!-- The name sits right next to it, so the emoji is decoration rather than content. -->
            <span class="assistant-detail-emoji" aria-hidden="true">{entry.emoji}</span>
                <span class="assistant-detail-names">
                    <span class="assistant-detail-name">{entry.assistant.label}</span>
                    <span class="assistant-detail-handle">{entry.assistant.handle}</span>
                </span>
            </span>
        <span class="assistant-detail-actions">
            <MenuPinButton
                kind="assistant"
                id={entry.assistant.id}
                variant="detail"
                {pinned}
                onToggle={togglePin}/>
            <button
                type="button"
                class="assistant-detail-toggle"
                role="switch"
                bind:this={toggleEl}
                aria-label={entry.active ? __('chat.composer.assistantMenu.untagAssistantAction') : __('chat.composer.assistantMenu.tagAssistantAction')}
                aria-checked={entry.active ? 'true' : 'false'}
                onpointerdowncapture={(e) => e.stopPropagation()}
                onclick={() => entry.onToggle(!entry.active)}>
                <Switch checked={entry.active} presentational/>
            </button>
        </span>
    </div>

    <p class="assistant-detail-description">{entry.assistant.description}</p>
</div>

<style>
    .assistant-detail {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
        min-width: 0;
        padding-bottom: var(--space-2, calc(0.25rem * 2));
    }

    .assistant-detail-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
        align-self: flex-start;
        margin-bottom: var(--space-1, 0.25rem);
        padding: var(--space-1, 0.25rem) var(--space-2, calc(0.25rem * 2));
        border: none;
        border-radius: var(--corner-sm);
        background: none;
        color: var(--color-text-muted, var(--color-text));
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms);
    }

    .assistant-detail-back:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .assistant-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
        padding-inline: var(--space-2, calc(0.25rem * 2));
    }

    .assistant-detail-title {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        min-width: 0;
    }

    /* Same swatch as `AssistantRow`: the tint has to come from behind the glyph, since
       emoji hues are fixed and don't line up with the assistant's own color. */
    .assistant-detail-emoji {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: calc(0.25rem * 8);
        height: calc(0.25rem * 8);
        border-radius: var(--corner-xs);
        background-color: color-mix(
            in oklab,
            color-mix(in oklab, var(--assistant-from), var(--assistant-to)) 15%,
            transparent
        );
        font-size: var(--font-size-sm);
        line-height: 1;
    }

    .assistant-detail-names {
        display: inline-flex;
        align-items: baseline;
        gap: var(--space-1, 0.25rem);
        min-width: 0;
    }

    .assistant-detail-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    /* Lightness-shifted exactly as in `AssistantRow`, so the handle reads the same in
       the list and in the detail panel. */
    .assistant-detail-handle {
        font-size: var(--font-size-xs);
        color: oklch(from var(--assistant-from) calc(l + var(--assistant-detail-handle-shift, -0.18)) c h);
    }

    :global(html.darkMode) .assistant-detail-handle {
        --assistant-detail-handle-shift: 0.12;
    }

    .assistant-detail-actions {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .assistant-detail-toggle {
        display: inline-flex;
        flex-shrink: 0;
        padding: 0;
        border: none;
        background: none;
        cursor: pointer;
    }

    .assistant-detail-description {
        margin: 0;
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted, var(--color-text));
    }
</style>
