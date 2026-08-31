<!--
  @component Sidebar control that lists the registered app modules in a
  `CommandPalette` and routes to the selected module's index route.
-->
<script lang="ts">
    import CommandPalette, {type CommandItemDefinition} from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    const sidebar = useSidebar();
    const app = useApp();
    const router = useRouter();

    const modules = $derived(app.modules.all);

    const { translate } = useTranslator();
    const locale = $derived(app.localization.locale);

    const moduleItems: CommandItemDefinition[] = $derived(modules.map((v) => {
        const icon = v.icon?.(locale);
        return {
            label: v.title?.(translate, locale) ?? v.name,
            value: `${v.plugin.name}:${v.name}`,
            // The palette renders icons as components; a string (URL) icon has no slot here.
            icon: typeof icon === 'string' ? undefined : icon as IconComponent | undefined
        };
    }));

    let open = $state(false);

    const current = $derived.by(() => {
        const module = modules.find(candidate =>
            router.isRouteActive(getModuleRouteGroupName(candidate.plugin.name, candidate.name)));
        return module ? `${module.plugin.name}:${module.name}` : moduleItems[0]?.value;
    });

    function selectModule(moduleId: string) {
        const module = modules.find(candidate => `${candidate.plugin.name}:${candidate.name}` === moduleId);
        if (!module) return;
        const prefix = getModuleRoutePrefix(module.plugin.name, module.name, module.plugin.isCorePlugin);
        void router.goTo(router.p(prefix));
    }

    function findModuleItem(value: string | undefined) {
        return moduleItems.find(item => item.value === value);
    }

    const currentModuleItem = $derived(findModuleItem(current));
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
