<!--
  @component Root of the `dropdown-menu` component family: a trigger plus an
  animated content panel. Wraps bits-ui's `DropdownMenu` and hides its
  low-level `Root`/`Trigger`/`Portal`/`Content` primitives behind a single
  component. Below the `--bp-md-and-smaller` breakpoint the content panel
  automatically renders as a `BottomSheet` instead of a floating popover —
  item components don't need to know which one is active.

  Compose it with the other family members as `children`:
  `DropdownMenuItem`, `DropdownMenuGroup`, `DropdownMenuLabel`,
  `DropdownMenuSeparator`, `DropdownMenuCheckboxItem`, `DropdownMenuSwitchItem`,
  `DropdownMenuRadioGroup` + `DropdownMenuRadioItem`, `DropdownMenuEmpty`,
  `DropdownMenuSection` for a group of items that folds away, and
  `DropdownMenuDetailView` for a nested two-panel picker.

  `layout="panel"` turns the content into the shape the composer's pickers use: a fixed
  `width`, a header that stays put (a search field, a title) and a `DropdownMenuDetailView`
  scrolling underneath it, instead of one scrolling block.

  ```svelte
  <DropdownMenu bind:open layout="panel" width="19rem">
      {#snippet trigger({props})}…{/snippet}
      <MenuSearchField bind:value={query} placeholder="Search"/>
      <DropdownMenuDetailView open={!!detail}>…rows…</DropdownMenuDetailView>
  </DropdownMenu>
  ```

  ```svelte
  <DropdownMenu bind:open title={__('chat.export.title')} align="end">
      {#snippet trigger({props})}
          <ButtonWithTooltip iconLeft={FileExportIcon} highlight={props['data-state']} {...props} />
      {/snippet}

      <DropdownMenuItem onclick={() => handleExport('pdf')}>
          {__('chat.export.pdf')}
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem variant="destructive" onclick={handleDelete}>
          {__('chat.export.delete')}
      </DropdownMenuItem>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, type DropdownMenuContentProps, mergeProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';

    interface Props {
        /** Whether the dropdown is open. Supports bind:open. */
        open?: boolean;
        /** When true, the menu cannot be opened. */
        disabled?: boolean;
        /** A title to render on top of the menu (both as dropdown and sheet). */
        title?: Snippet | string;
        /**
         * The element that opens the menu. Can be a string (rendered as a `<button>`)
         * or a Snippet that receives `{ props }` — the props MUST be spread on the
         * snippet's root element so accessibility and keyboard handling work correctly.
         */
        trigger?: Snippet<[{ props: Record<string, any> }]> | string;
        /** Menu items rendered inside the content panel. */
        children?: Snippet;
        /** Preferred side relative to the trigger. */
        side?: 'top' | 'right' | 'bottom' | 'left';
        /** Alignment relative to the trigger. Defaults to 'start' or 'end' based on screen position. */
        align?: 'start' | 'center' | 'end';
        /** Pixel offset from the trigger. */
        sideOffset?: number;
        /** Additional props forwarded to the DropdownMenu.Content element. */
        contentProps?: Omit<DropdownMenuContentProps, 'children'>;
        /** `list` (default) is one scrolling block of items. `panel` fixes the content's
         *  height to its box and stops it scrolling as a whole, so a header stays anchored
         *  while a `DropdownMenuDetailView` scrolls beneath it. */
        layout?: 'list' | 'panel';
        /** Fixed width of the dropdown, e.g. `'19rem'`. Without it the content sizes to its
         *  widest item — which makes it resize as items are filtered or folded away. Capped
         *  at the viewport either way. Ignored by the mobile sheet, which is full-width. */
        width?: string;
        /** Cap on the content's height, e.g. `'24rem'`. The available height below the
         *  trigger always wins when it is smaller. */
        maxHeight?: string;
    }

    let {
        open = $bindable(false),
        disabled,
        title,
        trigger,
        children,
        side = 'bottom',
        align = undefined,
        sideOffset = 4,
        contentProps,
        layout = 'list',
        width,
        maxHeight
    }: Props = $props();

    let triggerEl = $state<HTMLElement | null>(null);
    const onOpenChange = (openNew: boolean) => {
        open = openNew;
    };

    function resolvedAlign(): 'start' | 'center' | 'end' {
        if (align) return align;
        if (triggerEl) {
            const {left, right} = triggerEl.getBoundingClientRect();
            const mid = (left + right) / 2;
            return mid < window.innerWidth / 2 ? 'start' : 'end';
        }
        return 'center';
    }

    // Sizing travels as custom properties so the stylesheet keeps the caps and fallbacks;
    // the props only say how wide and how tall this particular menu may get.
    const sizeStyle = $derived([
        width ? `--dropdown-width: ${width};` : '',
        maxHeight ? `--dropdown-max-height: ${maxHeight};` : ''
    ].join(''));

    const fullContentProps = $derived.by(() => {
        return mergeProps(
            {
                side,
                align: resolvedAlign(),
                sideOffset,
                class: ['dropdown-content', layout === 'panel' && 'dropdown-content--panel'],
                style: sizeStyle || undefined
            },
            contentProps ?? {}
        ) as DropdownMenuContentProps;
    });
</script>

<DropdownMenuPrimitive.Root bind:open {onOpenChange}>
    {#if trigger}
        <DropdownMenuPrimitive.Trigger disabled={disabled}>
            {#snippet child({props})}
                {#if typeof trigger === 'string'}
                    <button bind:this={triggerEl} {...props} type="button">{trigger}</button>
                {:else}
                    <span bind:this={triggerEl} style="display:contents">
                        {@render trigger({props})}
                    </span>
                {/if}
            {/snippet}
        </DropdownMenuPrimitive.Trigger>
    {/if}
    <Breakpoint>
        {#snippet bpSmallerThanMd()}
            <BottomSheet bind:open={open} title={title}>
                <DropdownMenuPrimitive.ContentStatic {...mergeProps(fullContentProps, {class: 'dropdown-content--sheet'}) as any}>
                    {@render children?.()}
                </DropdownMenuPrimitive.ContentStatic>
            </BottomSheet>
        {/snippet}
        {#snippet children()}
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content {...mergeProps(fullContentProps, {class: 'dropdown-content--dropdown'}) as any}>
                    {#if title}
                        <div class="dropdown-title">
                            <SnippetOrString value={title}/>
                        </div>
                    {/if}
                    {@render children?.()}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        {/snippet}
    </Breakpoint>
</DropdownMenuPrimitive.Root>

<style>
    :global(.dropdown-content.dropdown-content--dropdown) {
        --dropdown-bg: var(--color-surface-raised);

        /* Above sticky/faded chrome like the chat header overlay. bits-ui copies
           this z-index onto the floating wrapper it positions the panel with. */
        z-index: var(--layer-overlay);
        min-width: 8rem;
        /* `width` fixes the box; without it the content still sizes to its items. Either way
           it stays inside the viewport. */
        width: var(--dropdown-width, auto);
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--dropdown-bg);
        padding: var(--space-1, 0.25rem);
        box-shadow: var(--elevation-1);
        max-height: min(
            var(--dropdown-max-height, 100vh),
            calc(var(--bits-dropdown-menu-content-available-height, 999px) - var(--space-4))
        );
        overflow: auto;
    }

    /*
      A panel doesn't scroll itself: it lays its children out in a column and leaves the
      scrolling to the one that owns it, so anything above (a search field, the title) stays
      anchored. The padding goes with it — the scrolling child pads its own rows, or the
      divider it draws would be clipped by this box's `overflow: hidden`.
    */
    :global(.dropdown-content.dropdown-content--dropdown.dropdown-content--panel) {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0;
    }

    :global(.dropdown-content--panel) .dropdown-title {
        padding-inline: var(--space-3);
        padding-bottom: 0;
    }

    :global(.dropdown-content[data-state="open"]) {
        animation: dropdown-in var(--duration-fast, 150ms) var(--easing-default, ease);
    }

    :global(.dropdown-content[data-state="closed"]) {
        animation: dropdown-out var(--duration-fast, 100ms) var(--easing-default, ease);
    }

    .dropdown-title {
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    @keyframes dropdown-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes dropdown-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.97;
        }
    }
</style>
