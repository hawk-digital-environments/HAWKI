<script lang="ts">
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import {useToolMenuFocusContext} from '$lib/components/chat/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        id: string;
        label: string;
        description: string | null;
    }

    const {id, label, description}: Props = $props();
    const focusContext = useToolMenuFocusContext();

    let triggerEl = $state<HTMLButtonElement | null>(null);

    $effect(() => {
        if (!triggerEl) return;
        return focusContext.register(`group-info:${id}`, triggerEl, 'group-info');
    });

    // bits-ui's menu wants Enter/Space to activate the focused row; for this
    // info trigger we swallow them so the underlying tool row never toggles,
    // and we drive arrow/Tab movement ourselves since the popover trigger isn't
    // part of the menu's roving tabindex.
    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.stopPropagation();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const direction: 1 | -1 = event.key === 'ArrowDown' ? 1 : -1;
            if (focusContext.focusAdjacent(`group-info:${id}`, direction)) {
                event.preventDefault();
                event.stopPropagation();
            }
            return;
        }
        if (event.key === 'Tab') {
            if (focusContext.focusAdjacent(`group-info:${id}`, event.shiftKey ? -1 : 1)) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
    }
</script>

<DropdownMenuLabel class="tool-menu-group-label">
    {label}
    {#if description}
        <InfoPopover
            bind:triggerEl
            info={description}
            ariaLabel={__('chat.composer.toolMenu.showGroupDescription', {label})}
            triggerProps={{
                tabindex: -1,
                onkeydown: onKeydown,
                onpointerdown: (e: Event) => e.stopPropagation(),
                onpointerup: (e: Event) => e.stopPropagation()
            }}/>
    {/if}
</DropdownMenuLabel>

<style>
    :global(.tool-menu-group-label) {
        display: flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
    }
</style>
