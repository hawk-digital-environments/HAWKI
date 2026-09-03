<!--
  @component Inline rename + actions dropdown for a chat's name.

  Shows the chat name as a `<span>` (or a click-to-rename button when
  `nameClickRenames` is set) next to a `DropdownMenu` trigger. With
  `showName={false}` the name is left to the parent — e.g. a sidebar row that
  renders it as a sibling button/link — and only the trigger is rendered here,
  so no interactive element ends up nested in another. When renaming is
  active (`isRenaming` bindable), the name (or the trigger) is swapped for an
  auto-focused text input that commits on Enter / blur and cancels on Escape;
  Enter on an empty name marks the field invalid (`aria-invalid` plus a linked
  message, and a toast) and stays in edit mode. Once renaming ends, focus goes
  back to the element that started it — or to whatever `focusAfterRename`
  returns. The default dropdown ships only a "Rename" item, but parent
  components extend it by passing `children` (e.g. `RoomNameMenu` adds
  manage/mark-read/leave/delete actions).

  Rename dispatches through `onNameChange(slug, newName)`; the default
  implementation forwards to `oldUiBridge.triggerRenameChat` so the legacy UI
  persists the change until the SPA owns this surface.

  @example
  <ChatNameMenu
      bind:name={chatName}
      bind:isRenaming={renaming}
      slug={chat.slug}
      triggerIcon={ChevronDownIcon}
  >
      <DropdownMenuItem onclick={...}>Extra action</DropdownMenuItem>
  </ChatNameMenu>
-->
<script lang="ts">

    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import {tick, type ComponentProps} from 'svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const toastContext = useToastContext();
    const {__} = useTranslator();

    interface Props extends HTMLAttributes<HTMLDivElement> {
        name: string;
        slug: string;
        onNameChange?: (slug: string, newName: string) => void;
        triggerIcon?: IconComponent;
        allowRename?: boolean;
        nameClickRenames?: boolean;
        /**
         * Whether the name itself is rendered here. Set to `false` when the parent
         * shows the name (e.g. as a sibling row button) and only wants the actions
         * trigger plus the inline rename field from this component.
         */
        showName?: boolean;
        /**
         * Additional props forwarded to the ButtonWithTooltip that triggers the menu.
         * Be careful with this, overriding certain props (like `tooltip`) can break the component's functionality or accessibility.
         */
        buttonProps?: Partial<ComponentProps<typeof ButtonWithTooltip>>;
        isRenaming?: boolean;
        /**
         * Whether the actions dropdown is open. Bindable, so a parent can e.g. keep
         * an otherwise hover-only trigger visible while its menu is showing.
         */
        open?: boolean;
        block?: boolean;
        /**
         * Where focus goes once renaming has ended and the input is gone. Defaults
         * to the element that started the rename (name button or menu trigger);
         * a parent that owns the row can point it at the row instead.
         */
        focusAfterRename?: () => HTMLElement | null | undefined;
    }

    let {
        name = $bindable(''),
        slug,
        onNameChange = (slug, newName) => oldUiBridge.triggerRenameChat(slug, newName),
        triggerIcon = ChevronDownIcon,
        allowRename = true,
        nameClickRenames = false,
        showName = true,
        buttonProps,
        isRenaming = $bindable(false),
        open = $bindable(false),
        block = false,
        focusAfterRename,
        children,
        ...restProps
    }: Props = $props();

    let renameInput: HTMLInputElement | null = $state(null);
    let renameHasIssue = $state(false);
    let nameButton = $state<HTMLButtonElement | null>(null);
    let triggerButton = $state<HTMLButtonElement | null>(null);
    const id = $props.id();
    const errorId = `${id}-rename-error`;

    // Removing the focused rename field drops focus onto <body>. Hand it back
    // to where the rename started — after a blur only when nothing else took
    // it, since the user may have tabbed or clicked elsewhere on purpose.
    async function finishRenaming(restoreFocus: boolean) {
        isRenaming = false;
        renameHasIssue = false;
        await tick();
        const focusLost = document.activeElement === null || document.activeElement === document.body;
        if (!restoreFocus && !focusLost) {
            return;
        }
        const target = focusAfterRename
            ? focusAfterRename()
            : (nameClickRenames && showName ? nameButton : triggerButton);
        target?.focus();
    }

    function dispatchRename(newName: string, restoreFocus: boolean) {
        if (!slug || !isRenaming) {
            return;
        }
        void finishRenaming(restoreFocus);
        if (newName === name) {
            return;
        }
        onNameChange(slug, newName);
    }

    function commitRename(newName: string, restoreFocus: boolean) {
        const trimmedName = newName.trim();
        if (!trimmedName) {
            if (restoreFocus) {
                renameHasIssue = true;
                toastContext.error(__('chat.nameMenu.emptyNameError'));
                return;
            }

            // An empty draft on blur cancels the rename. Do not trap focus or show a toast.
            void finishRenaming(false);
            return;
        }

        dispatchRename(trimmedName, restoreFocus);
    }

    function onRenameKeyDown(event: KeyboardEvent) {
        if (event.key === 'Enter') {
            event.preventDefault();
            commitRename((event.target as HTMLInputElement).value, true);
        }
        if (event.key === 'Escape') {
            void finishRenaming(true);
        }
    }

    $effect(() => {
        if (allowRename && isRenaming && renameInput) {
            setTimeout(() => {
                if (!renameInput) {
                    return;
                }
                renameInput.value = name; // Reset input value to current chat name when renaming starts, in case it was changed while not focused
                renameInput.focus();
                renameInput.select();
            });
        }
    });
</script>

<div {...mergeProps(
    {class: ['chat-name-menu', block && 'block', !showName && 'no-name', isRenaming && 'renaming']},
    restProps
)}>
    {#if allowRename && isRenaming}
        <!-- svelte-ignore a11y_autofocus -->
        <input
            bind:this={renameInput}
            onblur={(e) => commitRename((e.target as HTMLInputElement).value, false)}
            oninput={() => renameHasIssue = false}
            onkeydown={onRenameKeyDown}
            autofocus
            aria-label={__('chat.nameMenu.newNameAriaLabel')}
            aria-invalid={renameHasIssue || undefined}
            aria-describedby={renameHasIssue ? errorId : undefined}
            value={name}
            class={[
                "chat-name-input",
                renameHasIssue ? 'has-issue' : ''
            ]}
        />
        {#if renameHasIssue}
            <span class="u-sr-only" id={errorId}>{__('chat.nameMenu.emptyNameError')}</span>
        {/if}
    {:else}
        {#if showName}
            {#if nameClickRenames}
                <button
                    type="button"
                    class="chat-name click-to-rename"
                    bind:this={nameButton}
                    aria-label={__('chat.nameMenu.renameAriaLabel', {name})}
                    onclick={() => isRenaming = true}>
                    {name}
                </button>
            {:else}
                <span class="chat-name">{name}</span>
            {/if}
        {/if}
        <DropdownMenu bind:open>
            {#snippet trigger({props})}
                <ButtonWithTooltip bind:ref={triggerButton} {...mergeProps(
                    {
                        variant: 'ghost',
                        size: 'sm',
                        iconLeft: triggerIcon,
                        tooltip: __('chat.nameMenu.actionsTooltip'),
                        'aria-label': __('chat.nameMenu.actionsForChat', {name}),
                        highlight: props['data-state'],
                    },
                    props,
                    buttonProps as any,
                )}/>
            {/snippet}
            {#if allowRename && !!slug}
                <DropdownMenuItem onclick={() => isRenaming = true} icon={PencilEdit01Icon}>
                    {__('chat.nameMenu.rename')}
                </DropdownMenuItem>
            {/if}
            {@render children?.()}
        </DropdownMenu>
    {/if}
</div>

<style>
    .chat-name-menu {
        width: 100%;
        display: flex;
        align-items: center;
        gap: var(--space-1);
        flex-shrink: 1;

        &.block {
            justify-content: space-between;
        }

        /* Trigger-only mode sits next to the parent's own name element and
           must not push it aside; the rename field still takes the full row. */
        &.no-name:not(.renaming) {
            width: auto;
            flex-shrink: 0;
        }
    }

    /* Sits inline where the name text was, so it inherits the surrounding
       font and only adds a light field affordance instead of a full form input. */
    .chat-name-input {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        padding: var(--space-0_5) var(--space-1_5);
        height: auto;
        min-height: unset;
        border: 1px solid var(--color-border);
        border-radius: var(--corner-xs);
        background: var(--color-surface-raised);
        font: inherit;
        color: inherit;
        transition: border-color var(--duration-extra-fast) ease,
                    box-shadow var(--duration-extra-fast) ease;

        &:focus,
        &:focus-visible {
            outline: none;
            border-color: var(--color-focus-ring);
            box-shadow: 0 0 0 3px color-mix(in oklab, var(--color-focus-ring) 25%, transparent);
        }

        &.has-issue {
            border-color: var(--color-error);

            &:focus,
            &:focus-visible {
                border-color: var(--color-error);
                box-shadow: 0 0 0 3px color-mix(in oklab, var(--color-error) 25%, transparent);
            }
        }
    }

    /* Grow/shrink with the typed name instead of spanning the full row.
       Falls back to the full-width field where field-sizing is unsupported. */
    @supports (field-sizing: content) {
        .chat-name-input {
            width: auto;
            field-sizing: content;
            min-width: 6rem;
        }
    }

    .chat-name {
        /* Reset button styles */
        padding: 0;
        height: auto;
        min-height: unset;
        background: transparent;
        font: inherit;
        color: inherit;
        border: none;
        cursor: inherit;
        /* Text truncation */
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;

        &.click-to-rename {
            cursor: text;
        }
    }
</style>
