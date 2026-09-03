<!--
  @component Sidebar control that lists the collected module selector entries
  in a `CommandPalette` and runs the selected entry's `onSelect`. The entries
  are collected from other plugins via the `moduleSelectorEntries` hook
  (see `useSidebarHooks.svelte.ts`).
-->
<script lang="ts">
    import CommandPalette, {type CommandItemDefinition} from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useModuleSelectorEntries} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    const sidebar = useSidebar();
    const app = useApp();
    const router = useRouter();
    const {translate} = useTranslator();

    const selectorEntries = useModuleSelectorEntries();
    const entries = $derived(selectorEntries.entries);

    const moduleItems: CommandItemDefinition[] = $derived(entries.map((entry) => ({
        label: entry.label,
        value: entry.id,
        // The palette renders icons as components; a string (URL) icon has no slot here.
        icon: typeof entry.icon === 'string' ? undefined : entry.icon as IconComponent | undefined
    })));

    let open = $state(false);

    const current = $derived(entries.find(entry => entry.active)?.id ?? moduleItems[0]?.value);

    function selectModule(value: string) {
        const entry = entries.find(candidate => candidate.id === value);
        entry?.onSelect({locale: app.localization.locale, translate, router});
    }

    const currentModuleItem = $derived(moduleItems.find(item => item.value === current));
</script>

<CommandPalette items={moduleItems} bind:open {current} onSelect={selectModule}>
    {#snippet trigger({ props })}
        <CommandPaletteTrigger
            label={currentModuleItem?.label ?? current ?? ''}
            icon={currentModuleItem?.icon}
            collapsed={!sidebar.navOpen}
            {...props}
        />
    {/snippet}
</CommandPalette>
