<script
    module
    lang="ts"
>
    import type { IconComponent } from '$lib/components/ui/icons/index.js';

    export interface AdminMenuItem {
        label: string;
        icon?: IconComponent;
        run: (trigger: HTMLButtonElement | null) => unknown;
        disabled?: boolean;
        destructive?: boolean;
    }
</script>

<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import MoreHorizontalIcon from '$lib/components/ui/icons/iconset/MoreHorizontalIcon.svelte';
    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

    let {
        label,
        items,
        disabled = false,
        compact = false,
        dialogOpen = false
    }: {
        label: string;
        items: AdminMenuItem[];
        disabled?: boolean;
        compact?: boolean;
        dialogOpen?: boolean;
    } = $props();
    const { __ } = useTranslator();
    let menuTrigger = $state<HTMLButtonElement | null>(null);
</script>

{#if items.length}
    <DropdownMenu
        {disabled}
        align="end"
        contentProps={{
            onCloseAutoFocus: (event) => {
                if (dialogOpen) event.preventDefault();
            }
        }}
    >
        {#snippet trigger({ props })}
            <Button
                {...props}
                bind:ref={menuTrigger}
                {disabled}
                aria-label={label}
                variant={compact ? 'ghost' : 'stroke'}
                size={compact ? 'sm' : 'md'}
                iconLeft={compact ? MoreHorizontalIcon : undefined}
                iconRight={compact ? undefined : ArrowDown01Icon}
            >
                {#if !compact}{__('admin.menu')}{/if}
            </Button>
        {/snippet}
        {#each items as item, index}
            {#if item.destructive && index > 0 && !items[index - 1].destructive}<DropdownMenuSeparator />{/if}
            <DropdownMenuItem
                disabled={item.disabled}
                icon={item.icon}
                variant={item.destructive ? 'destructive' : 'default'}
                onSelect={() => item.run(menuTrigger)}
            >
                {item.label}
            </DropdownMenuItem>
        {/each}
    </DropdownMenu>
{/if}
