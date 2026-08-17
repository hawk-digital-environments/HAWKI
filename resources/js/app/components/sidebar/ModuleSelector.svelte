<script lang="ts">
    import CommandPalette, { type CommandItemDefinition } from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import { useSidebar } from '$lib/components/ui/sidebar/SidebarState.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte';
    import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';

    const sidebar = useSidebar()

    // Rendered outside the RouterView subtree, so the router context set there
    // is not reachable — the app-level handle is used instead.
    const app = useApp();

    const modules = $derived(app.modules.all);

    const { translate } = useTranslator();
    const locale = $derived(app.localization.locale);

    const commands: CommandItemDefinition[] = $derived(modules.map((v) => ({
        label: v.title?.(translate, locale) ?? v.name,
        value: `${v.plugin.name}:${v.name}`,
        icon: v.icon?.(locale)
    })))

    let open = $state(false)

    const current = $derived.by(() => {
        const module = modules.find(candidate =>
            app.router.isRouteActive(getModuleRouteGroupName(candidate.plugin.name, candidate.name)));
        return module ? `${module.plugin.name}:${module.name}` : commands[0]?.value;
    })

    function selectCommand(command: string) {
        const module = modules.find(candidate => `${candidate.plugin.name}:${candidate.name}` === command);
        if (!module) return;
        const prefix = getModuleRoutePrefix(module.plugin.name, module.name, module.plugin.isCorePlugin);
        void app.router.goTo(app.router.p(prefix));
    }

    function findCommand(value: string | undefined) {
        return commands.find(c => c.value === value)
    }

    const currentCommand = $derived(findCommand(current))
</script>

<CommandPalette items={commands} bind:open {current} onSelect={selectCommand}>
    {#snippet trigger({ props })}
        <CommandPaletteTrigger
            label={currentCommand?.label ?? current ?? ''}
            icon={currentCommand?.icon}
            collapsed={!sidebar.navOpen}
            {...props}
        />
    {/snippet}
</CommandPalette>
