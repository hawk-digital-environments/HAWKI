<!--
  @component Sidebar control that lists the registered app modules in a
  `CommandPalette` and routes to the selected module's index route.

  The highlighted entry is the `module` the parent resolved (so it stays in
  sync with the module sidebar on routes that belong to no module, like the
  announcements page); the first entry is only a fallback for an empty prop.
-->
<script lang="ts">
    import CommandPalette, {type CommandItemDefinition} from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import type {HawkiCoreModule, HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';

    interface Props {
        /** Module to show as selected; defaults to the first listed module. */
        module?: HawkiModuleWithPlugin | null;
    }

    const {module = null}: Props = $props();

    const sidebar = useSidebar();
    const app = useApp();
    const router = useRouter();

    const modules = $derived(app.modules.all.filter(module => module.routes && (module.visible?.(app) ?? true)));

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

    const current = $derived(module ? `${module.plugin.name}:${module.name}` : moduleItems[0]?.value);

    // NOTE: the forth argument "(module as HawkiCoreModule).pluginNameInRoutes" was added to the function
    // assistant/dashboard was landing in /dashboard
    function selectModule(moduleId: string) {
        const module = modules.find(candidate => `${candidate.plugin.name}:${candidate.name}` === moduleId);
        if (!module) return;
        const prefix = getModuleRoutePrefix(
            module.plugin.name,
            module.name,
            module.plugin.isCorePlugin,
            (module as HawkiCoreModule).pluginNameInRoutes
        );
        void router.goTo(router.p(prefix));
    }

    function findModuleItem(value: string | undefined) {
        return moduleItems.find(item => item.value === value);
    }

    // The `module` prop can be one that has no row of its own in the list
    // (e.g. the assistants builder, hidden from the selector — see
    // `BuilderModule.visible()`): it is still the active module, just not
    // one you can *pick*. The trigger button must still show its own
    // label/icon in that case, computed the same way `moduleItems` computes
    // everyone else's — falling back to `current`'s raw value only if the
    // module defines neither.
    const currentModuleItem = $derived(findModuleItem(current) ?? (module ? {
        label: module.title?.(translate, locale) ?? module.name,
        value: current,
        icon: (icon => typeof icon === 'string' ? undefined : icon as IconComponent | undefined)(module.icon?.(locale))
    } : undefined));
</script>

<CommandPalette items={moduleItems} bind:open {current} onSelect={selectModule} shortcut={false}>
    {#snippet trigger({ props })}
        <CommandPaletteTrigger
            label={currentModuleItem?.label ?? current ?? ''}
            icon={currentModuleItem?.icon}
            collapsed={!sidebar.navOpen}
            {...props}
        />
    {/snippet}
</CommandPalette>
