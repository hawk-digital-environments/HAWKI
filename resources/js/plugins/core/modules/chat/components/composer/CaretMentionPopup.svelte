<!--
  @component The caret-anchored autocomplete popup shared by the composer's `@` (assistant)
  and `/` (tool) menus: it owns the chrome, the positioning and the keyboard-driven
  scrolling, and leaves what a row looks like to the `row` snippet its caller passes.

  This list never takes focus — the textarea keeps it, and `ComposerTextarea` drives
  selection with ArrowUp/ArrowDown/Enter/Tab/Escape. It is therefore a `listbox` the
  textarea points at via `aria-activedescendant`, not a menu.

  Positioned `fixed` at the caret rect, preferring the space above the caret (the composer
  sits at the bottom of the screen) and flipping below when there isn't room. It renders
  through a `Portal`, because the composer card clips (`overflow: hidden`) and — via its
  `backdrop-filter` — also acts as the containing block for fixed descendants, so an
  in-place popup would be positioned against the card and then clipped away.

  ## Usage
  ```svelte
  <CaretMentionPopup
      items={filteredAssistants}
      key={(assistant) => assistant.id}
      optionId={assistantMentionOptionId}
      label={__('chat.composer.assistantMenu.title')}
      caret={caretRect}
      activeIndex={mentionIndex}
      onActivate={(index) => (mentionIndex = index)}
      onSelect={insertMention}>
      {#snippet row(assistant)}
          <AssistantRow .../>
      {/snippet}
  </CaretMentionPopup>
  ```
-->
<script lang="ts" generics="T">
    import type {Snippet} from 'svelte';
    import type {CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';
    import {Portal} from 'bits-ui';

    interface Props {
        /** The entries to offer, in display order. Never empty — `ComposerTextarea` closes
         *  the popup instead of rendering an empty list. */
        items: T[];
        /** Stable key per entry, used as the `{#each}` key. */
        key: (item: T) => string;
        /** DOM id of the option at `index`, so the textarea can point at the active one
         *  via `aria-activedescendant`. */
        optionId: (index: number) => string;
        /** Accessible name of the listbox. */
        label: string;
        /** Viewport-relative caret position the popup anchors to. */
        caret: CaretRect;
        /** Index of the highlighted entry, driven by the textarea's arrow keys and by
         *  hovering a row. */
        activeIndex: number;
        /** Called when the pointer moves over a row, so hovering moves the highlight —
         *  otherwise Enter and a click could target two different entries. */
        onActivate: (index: number) => void;
        /** Called with the entry to apply (click or Enter/Tab in the textarea). */
        onSelect: (item: T) => void;
        /** Renders the contents of one option row. */
        row: Snippet<[T]>;
    }

    const {items, key, optionId, label, caret, activeIndex, onActivate, onSelect, row}: Props = $props();

    const GAP = 4;

    let popupHeight = $state(0);
    let popupWidth = $state(0);
    let popupEl = $state<HTMLDivElement | null>(null);

    // Arrow keys move the highlight without moving focus, so the browser does no scrolling
    // of its own — keep the active option visible manually. Looked up by id rather than by
    // a bound ref array, so a shrinking filtered list can't leave a stale element behind.
    //
    // Scrolled by hand instead of via `scrollIntoView({block: 'nearest'})`, because that
    // aligns the option flush with the padding box: at either end of the list the first/last
    // row would sit tight against the popup's border. Reserving the popup's own padding
    // around the option keeps the resting state at the top/bottom of the list identical to
    // the un-scrolled one. The animation itself comes from the container's `scroll-behavior`,
    // which also honours `prefers-reduced-motion`.
    $effect(() => {
        const container = popupEl;
        const option = container?.querySelector(`#${CSS.escape(optionId(activeIndex))}`);
        if (!container || !option) {
            return;
        }

        const styles = window.getComputedStyle(container);
        const paddingTop = parseFloat(styles.paddingTop) || 0;
        const paddingBottom = parseFloat(styles.paddingBottom) || 0;

        // Option bounds in the container's scroll coordinates (clientTop drops the border,
        // which the rects include but scrollTop does not).
        const optionRect = option.getBoundingClientRect();
        const optionTop = optionRect.top
            - container.getBoundingClientRect().top
            - container.clientTop
            + container.scrollTop;
        const optionBottom = optionTop + optionRect.height;

        // Reveal the neighbouring row along with the target one, so a run of arrow presses
        // scrolls in fewer, larger steps instead of nudging by a row at a time.
        const lead = optionRect.height;

        if (optionTop - paddingTop < container.scrollTop) {
            container.scrollTop = Math.max(optionTop - paddingTop - lead, 0);
        } else if (optionBottom + paddingBottom > container.scrollTop + container.clientHeight) {
            container.scrollTop = Math.min(
                optionBottom + paddingBottom + lead - container.clientHeight,
                container.scrollHeight - container.clientHeight
            );
        }
    });

    // Prefer opening upwards (the composer lives at the bottom of the viewport) and flip
    // below the caret line only when there isn't enough room above.
    const position = $derived.by(() => {
        const opensUpwards = caret.top - popupHeight - GAP >= 0;
        const top = opensUpwards
            ? caret.top - popupHeight - GAP
            : caret.top + caret.height + GAP;
        const maxLeft = Math.max(window.innerWidth - popupWidth - GAP, GAP);
        return {top, left: Math.min(Math.max(caret.left, GAP), maxLeft)};
    });
</script>

<!--
  Pointer events must not move focus out of the textarea, otherwise the caret (and with it
  the mention we are about to replace) is lost before the click lands.
-->
<Portal>
    <div
        class="caret-mention-popup"
        role="listbox"
        tabindex="-1"
        aria-label={label}
        bind:this={popupEl}
        bind:clientHeight={popupHeight}
        bind:clientWidth={popupWidth}
        style:top="{position.top}px"
        style:left="{position.left}px"
        onpointerdown={(event) => event.preventDefault()}>
        {#each items as item, index (key(item))}
            <button
                type="button"
                id={optionId(index)}
                class="caret-mention-option"
                class:caret-mention-option--active={index === activeIndex}
                role="option"
                tabindex="-1"
                aria-selected={index === activeIndex}
                onmousemove={() => onActivate(index)}
                onclick={() => onSelect(item)}>
                {@render row(item)}
            </button>
        {/each}
    </div>
</Portal>

<style>
    /* Matches the dropdown-menu content chrome so both entry points look like one feature. */
    .caret-mention-popup {
        position: fixed;
        z-index: 50;
        width: calc(0.25rem * 76);
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
        max-height: 15rem;
        overflow-y: auto;
        overscroll-behavior: contain;
        scroll-behavior: smooth;
        /* The list is driven by the keyboard and stays close to the caret, so the
           scrollbar would be more visual noise than affordance. */
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--color-surface-raised);
        padding: var(--space-1, 0.25rem);
        box-shadow: var(--elevation-1);
        animation: caret-mention-in var(--duration-fast, 150ms) var(--easing-default, ease);
    }

    @media (prefers-reduced-motion: reduce) {
        .caret-mention-popup {
            scroll-behavior: auto;
        }
    }

    .caret-mention-popup::-webkit-scrollbar {
        display: none;
    }

    .caret-mention-option {
        display: flex;
        width: 100%;
        border: none;
        background: none;
        color: inherit;
        font: inherit;
        text-align: start;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        border-radius: var(--corner-sm);
        padding-block: var(--space-1_5);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        cursor: pointer;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
    }

    .caret-mention-option--active {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    @keyframes caret-mention-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }
</style>
