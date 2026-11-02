<!--
  @component Sidebar control that lists the registered app modules in a
  `CommandPalette` and routes to the selected module's index route.
-->
<script lang="ts">
    import CommandPalette, {type CommandItemDefinition} from '$lib/components/ui/command/CommandPalette.svelte';
    import CommandPaletteTrigger from '$lib/components/ui/command/CommandPaletteTrigger.svelte';
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import {isApple} from '$lib/utils/platform.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    const app = useApp();
    const router = useRouter();

    const modules = $derived(app.modules.all.filter(module => module.routes));

    const { translate } = useTranslator();
    const locale = $derived(app.localization.locale);

    const moduleItems: CommandItemDefinition[] = $derived(modules.map((v, index) => {
        const icon = v.icon?.(locale);
        return {
            label: v.title?.(translate, locale) ?? v.name,
            value: `${v.plugin.name}:${v.name}`,
            // The palette renders icons as components; a string (URL) icon has no slot here.
            icon: typeof icon === 'string' ? undefined : icon as IconComponent | undefined,
            // Only the digits 1-9 are available as keys.
            shortcut: index < 9 ? `${isApple ? '⌘' : 'Ctrl '}${index + 1}` : undefined
        };
    }));

    let {open = $bindable(false)}: {open?: boolean} = $props();

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

    function switchByShortcut(moduleId: string) {
        // The model picker claims the same digits while it is open, and a switch
        // shouldn't happen behind any other open menu or dialog either.
        if (!open && document.querySelector('[role="menu"], [role="dialog"], [role="listbox"]')) return;
        // Already there, so the active space's shortcut toggles the menu instead.
        if (moduleId === current) {
            open = !open;
            return;
        }
        open = false;
        selectModule(moduleId);
    }

    function findModuleItem(value: string | undefined) {
        return moduleItems.find(item => item.value === value);
    }

    const currentModuleItem = $derived(findModuleItem(current));
</script>

<CommandPalette
    items={moduleItems}
    bind:open
    {current}
    label={translate('ui.navigation.spaces')}
    onSelect={selectModule}
    shortcut={false}
    contained
>
    {#snippet trigger({ props })}
        <CommandPaletteTrigger
            label={currentModuleItem?.label ?? current ?? ''}
            icon={currentModuleItem?.icon}
            shortcut={currentModuleItem?.shortcut}
            {...props}
        />
    {/snippet}
</CommandPalette>

<!-- The palette rows show the hints, so the hotkeys render no keycap. -->
{#each moduleItems.slice(0, 9) as item, index (item.value)}
    <Kbd key={String(index + 1)} ctrl onPress={() => switchByShortcut(item.value)}>
        {#snippet children()}{/snippet}
    </Kbd>
{/each}
