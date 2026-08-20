<script lang="ts">
    import CommandPalette, {type CommandItemDefinition} from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';

    const sidebar = useSidebar();
    const app = useApp();
    const router = useRouter();

    const modules = $derived(app.modules.all);

    const { translate } = useTranslator();
    const locale = $derived(app.localization.locale);

    const moduleItems: CommandItemDefinition[] = $derived(modules.map((v) => ({
        label: v.title?.(translate, locale) ?? v.name,
        value: `${v.plugin.name}:${v.name}`,
        icon: v.icon?.(locale)
    })));

    let open = $state(false);

    const current = $derived.by(() => {
        const module = modules.find(candidate =>
            router.isRouteActive(getModuleRouteGroupName(candidate.plugin.name, candidate.name)));
        return module ? `${module.plugin.name}:${module.name}` : moduleItems[0]?.value;
    });

    function selectCommand(command: string) {
        const module = modules.find(candidate => `${candidate.plugin.name}:${candidate.name}` === command);
        if (!module) return;
        const prefix = getModuleRoutePrefix(module.plugin.name, module.name, module.plugin.isCorePlugin);
        void router.goTo(router.p(prefix));
    }

    function findCommand(value: string | undefined) {
        return moduleItems.find(c => c.value === value);
    }

    const currentCommand = $derived(findCommand(current));
</script>

<CommandPalette items={moduleItems} bind:open {current} onSelect={selectCommand}>
    {#snippet trigger({ props })}
        <CommandPaletteTrigger
            label={currentCommand?.label ?? current ?? ''}
            icon={currentCommand?.icon}
            collapsed={!sidebar.navOpen}
            {...props}
        />
    {/snippet}
</CommandPalette>
