<script lang="ts">

    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import type {ComponentProps} from 'svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';

    const toastContext = useToastContext();

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
