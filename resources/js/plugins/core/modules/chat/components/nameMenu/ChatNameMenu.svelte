<!--
  @component Inline rename + actions dropdown for a chat's name.

  Shows the chat name as a `<span>` (or a click-to-rename button when
  `nameClickRenames` is set) next to a `DropdownMenu` trigger. When renaming is
  active (`isRenaming` bindable), the name is swapped for an auto-focused text
  input that commits on Enter / blur and cancels on Escape; Enter on an empty
  name shows an error toast and stays in edit mode. The default dropdown ships
  only a "Rename" item, but parent components extend it by passing `children`
  (e.g. `RoomNameMenu` adds manage/mark-read/leave/delete actions).

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

    import type {ComponentProps} from 'svelte';
    import {ButtonWithTooltip, DropdownMenu, DropdownMenuItem, useToastContext, type IconComponent} from '@hawk-hhg/hawki-svelte-components';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
    import ChevronDownIcon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/ChevronDownIcon.svelte';
    import PencilEdit01Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/PencilEdit01Icon.svelte';
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
         * Additional props forwarded to the ButtonWithTooltip that triggers the menu.
         * Be careful with this, overriding certain props (like `tooltip`) can break the component's functionality or accessibility.
         */
        buttonProps?: Partial<ComponentProps<typeof ButtonWithTooltip>>;
        isRenaming?: boolean;
        block?: boolean;
    }

    let {
        name = $bindable(''),
        slug,
        onNameChange = (slug, newName) => oldUiBridge.triggerRenameChat(slug, newName),
        triggerIcon = ChevronDownIcon,
        allowRename = true,
        nameClickRenames = false,
        buttonProps,
        isRenaming = $bindable(false),
        block = false,
        children,
        ...restProps
    }: Props = $props();

    let renameInput: HTMLInputElement | null = $state(null);
    let renameHasIssue = $state(false);

    function dispatchRename(newName: string) {
        if (!slug || !isRenaming) {
            return;
        }
        if (newName === name) {
            isRenaming = false;
            return;
        }
        onNameChange(slug, newName);
        isRenaming = false;
    }

    function onRenameKeyDown(event: KeyboardEvent) {
        if (event.key === ' ') {
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
        if (event.key === 'Enter') {
            event.stopPropagation();
            const newName = (event.target as HTMLInputElement).value;
            if (!newName.trim()) {
                renameHasIssue = true;
                toastContext.error(__('chat.nameMenu.emptyNameError'));
                return;
            }
            dispatchRename((event.target as HTMLInputElement).value);
        }
        if (event.key === 'Escape') {
            isRenaming = false;
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
    {class: ['chat-name-menu', block && 'block']},
    restProps
)}>
    {#if allowRename && isRenaming}
        <!-- Stop clicks and focus events from bubbling to parent elements (e.g. sidebar buttons) while renaming-->
        <!-- svelte-ignore a11y_autofocus -->
        <input
            bind:this={renameInput}
            onclick={(e) => e.preventDefault()}
            onblur={(e) => dispatchRename((e.target as HTMLInputElement).value)}
            onkeydown={onRenameKeyDown}
            autofocus
            aria-label={__('chat.nameMenu.newNameAriaLabel')}
            value={name}
            class={[
                "chat-name-input",
                renameHasIssue ? 'has-issue' : ''
            ]}
        />
    {:else}
        {#if nameClickRenames}
            <button class="chat-name click-to-rename" onclick={() => isRenaming = true}>
                {name}
            </button>
        {:else}
            <span class="chat-name">{name}</span>
        {/if}
        <DropdownMenu>
            {#snippet trigger({props})}
                <ButtonWithTooltip {...mergeProps(
                    {
                        variant: 'ghost',
                        size: 'sm',
                        iconLeft: triggerIcon,
                        tooltip: __('chat.nameMenu.actionsTooltip'),
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
    }

    .chat-name-input {
        padding: var(--space-0_5);
        height: auto;
        min-height: unset;
        background: transparent;

        &.has-issue {
            outline-color: var(--color-error);
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
