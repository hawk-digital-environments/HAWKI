<!--
  @component The sidebar's space switcher: the pinned spaces as collapsible tabs,
  where only the space you are in shows its name, and an add button at the end
  of the row that opens a picker for pinning and unpinning spaces.

  Until the user first changes their pins, every space is pinned. A space that
  is open but not pinned still gets a tab while you are in it, after the pinned
  ones. Pins are a per-browser display preference, like the composer's, so they
  live in local storage.

  Cmd+1…9 (Ctrl off Apple) switch to the pinned tabs in order, the way browser
  tabs do; holding the modifier shows each tab's number in place of its icon.
-->
<script lang="ts">
    import CollapsibleTabs from '$lib/components/ui/tabs/CollapsibleTabs.svelte';
    import type {CollapsibleTabItem} from '$lib/components/ui/tabs/CollapsibleTab.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuCheckboxItem from '$lib/components/ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
    import SidebarIconButton from '$lib/components/ui/sidebar/SidebarIconButton.svelte';
    import TooltipGroup from '$lib/components/ui/tooltip/TooltipGroup.svelte';
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import PlusSignIcon from '$lib/components/ui/icons/iconset/PlusSignIcon.svelte';
    import PinIcon from '$lib/components/ui/icons/iconset/PinIcon.svelte';
    import PinOffIcon from '$lib/components/ui/icons/iconset/PinOffIcon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    const STORAGE_KEY = 'hawki.sidebar.pinnedSpaces';

    interface Space {
        id: string;
        label: string;
        icon?: IconComponent;
    }

    const app = useApp();
    const router = useRouter();
    const {__, translate} = useTranslator();

    const modules = $derived(app.modules.all.filter(module => module.routes));
    const locale = $derived(app.localization.locale);

    const spaces: Space[] = $derived(modules.map(module => {
        const icon = module.icon?.(locale);
        return {
            id: `${module.plugin.name}:${module.name}`,
            label: module.title?.(translate, locale) ?? module.name,
            // The tabs render icons as components; a string (URL) icon has no slot here.
            icon: typeof icon === 'string' ? undefined : icon as IconComponent | undefined
        };
    }));

    const current = $derived.by(() => {
        const module = modules.find(candidate =>
            router.isRouteActive(getModuleRouteGroupName(candidate.plugin.name, candidate.name)));
        return module ? `${module.plugin.name}:${module.name}` : spaces[0]?.id ?? null;
    });

    // Null until the user first pins or unpins, so that until then spaces registered
    // later are pinned too.
    let storedPins = $state<string[] | null>(readPins());
    const pinnedIds = $derived(storedPins ?? spaces.map(space => space.id));
    // In the order they were pinned; pins of spaces that no longer exist drop out.
    const pinnedSpaces = $derived(pinnedIds
        .map(id => spaces.find(space => space.id === id))
        .filter((space): space is Space => space !== undefined));

    const tabs: CollapsibleTabItem[] = $derived.by(() => {
        const items: CollapsibleTabItem[] = pinnedSpaces.map((space, index) => ({
            key: space.id,
            label: space.label,
            icon: space.icon,
            // Only the digits 1-9 are available as keys.
            shortcut: index < 9 ? String(index + 1) : undefined
        }));
        const open = spaces.find(space => space.id === current);
        if (open && !pinnedIds.includes(open.id)) {
            items.push({key: open.id, label: open.label, icon: open.icon});
        }
        return items;
    });

    function readPins(): string[] | null {
        try {
            const parsed: unknown = JSON.parse(app.localStorage.getItem(STORAGE_KEY) ?? 'null');
            return Array.isArray(parsed) ? parsed.filter((id): id is string => typeof id === 'string') : null;
        } catch {
            // A user-editable storage key is never trusted: unreadable pins are the default.
            return null;
        }
    }

    function togglePin(id: string) {
        storedPins = pinnedIds.includes(id) ? pinnedIds.filter(pinned => pinned !== id) : [...pinnedIds, id];
        app.localStorage.setItem(STORAGE_KEY, JSON.stringify(storedPins));
    }

    function selectSpace(id: string) {
        const module = modules.find(candidate => `${candidate.plugin.name}:${candidate.name}` === id);
        if (!module) return;
        const prefix = getModuleRoutePrefix(module.plugin.name, module.name, module.plugin.isCorePlugin);
        void router.goTo(router.p(prefix));
    }

    function switchByShortcut(id: string) {
        // The model picker claims the same digits while it is open, and a switch
        // shouldn't happen behind any other open menu or dialog either.
        if (id === current || document.querySelector('[role="menu"], [role="dialog"], [role="listbox"]')) return;
        selectSpace(id);
    }
</script>

<!-- The tabs' tooltips and the add button's are one bubble that travels along the row. -->
<TooltipGroup delayDuration={300}>
    <div class="space-tabs">
        <CollapsibleTabs items={tabs} value={current} onChange={selectSpace} aria-label={__('ui.navigation.spaces')}/>
        {#if spaces.length > 1}
            <DropdownMenu align="end">
                {#snippet trigger({props})}
                    <SidebarIconButton icon={PlusSignIcon} label={__('ui.navigation.pinSpaces')} {...props}/>
                {/snippet}
                <!-- In a fixed order, so a row stays under the pointer when it is pinned. -->
                {#each spaces as space (space.id)}
                    <DropdownMenuCheckboxItem
                        checked={pinnedIds.includes(space.id)}
                        onCheckedChange={() => togglePin(space.id)}
                        closeOnSelect={false}
                        indicator="none"
                        iconLeft={space.icon}
                    >
                        {#snippet children(pinned)}
                            <span class="space-label">{space.label}</span>
                            <!-- The composer pickers' pin glyph: shown on hover until pinned. -->
                            <span class="pin" class:pinned aria-hidden="true">
                                {#if pinned}
                                    <PinOffIcon size={15}/>
                                {:else}
                                    <PinIcon size={15}/>
                                {/if}
                            </span>
                        {/snippet}
                    </DropdownMenuCheckboxItem>
                {/each}
            </DropdownMenu>
        {/if}
    </div>
</TooltipGroup>

<!-- The tabs show the numbers, so the hotkeys render no keycap. -->
{#each pinnedSpaces.slice(0, 9) as space, index (space.id)}
    <Kbd key={String(index + 1)} ctrl onPress={() => switchByShortcut(space.id)}>
        {#snippet children()}{/snippet}
    </Kbd>
{/each}

<style>
    /* The add button keeps to the row's end, under the header's search, so pinning
       never moves it — or the open picker hanging off it. */
    .space-tabs {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .space-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pin {
        display: flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
        opacity: var(--dropdown-item-action-opacity, 0);
    }

    .pin.pinned {
        opacity: 1;
        color: var(--color-text);
    }
</style>
