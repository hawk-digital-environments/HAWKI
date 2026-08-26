<!--
  @component Trigger + panel for the composer's model settings, built on the
  `DropdownMenu` family (floating menu on `bpMd`-and-bigger viewports, a
  `BottomSheet` below that — handled by `DropdownMenu` itself).

  The panel is composed of three sections, each owning its own state and
  styles and talking to `ComposerContext` directly:
  - `SettingsMenuSystemPrompt` — preview, reset and dialog for the system prompt.
  - `SettingsMenuReasoning` — reasoning effort as a `DropdownMenuSub` (hidden
    for models without adjustable reasoning).
  - `SettingsMenuSampling` — temperature/Top P presets and sliders.

  The "Settings" heading's reset button restores the model's default
  parameters via `modelParameters.reset()`. Disabled as a whole when
  `composerContext.guard.disablesFeature('settings')` is true (e.g. during
  edit/regen mode). Takes no props.

  Keyboard: bits-ui menus move focus with ↑/↓ between menu items only and
  treat Tab as "leave the menu". Since this panel also contains plain
  controls (system prompt preview, expander, sliders, tabs), Tab/Shift+Tab is
  intercepted on the panel body (before it bubbles to the menu content) and
  cycles through every focusable control inside the panel instead.
-->
<script lang="ts">
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import SettingsMenuSectionHeader from '$plugins/core/modules/chat/components/composer/SettingsMenuSectionHeader.svelte';
    import SettingsMenuSystemPrompt from '$plugins/core/modules/chat/components/composer/SettingsMenuSystemPrompt.svelte';
    import SettingsMenuReasoning from '$plugins/core/modules/chat/components/composer/SettingsMenuReasoning.svelte';
    import SettingsMenuSampling from '$plugins/core/modules/chat/components/composer/SettingsMenuSampling.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    let settingsOpen = $state(false);

    const modifiedParameters = $derived.by(() => composerContext.modelParameters.isModified);

    const FOCUSABLE = [
        'button:not([disabled]):not([aria-disabled="true"]):not([tabindex="-1"])',
        '[role="menuitem"]:not([data-disabled])',
        '[role="slider"]',
        '[role="tab"][tabindex="0"]'
    ].join(', ');

    function cycleFocusOnTab(event: KeyboardEvent) {
        if (event.key !== 'Tab' || !(event.currentTarget instanceof HTMLElement)) return;
        const focusables = Array.from(event.currentTarget.querySelectorAll<HTMLElement>(FOCUSABLE));
        if (focusables.length === 0) return;
        event.preventDefault();
        event.stopPropagation();
        const current = focusables.indexOf(document.activeElement as HTMLElement);
        const step = event.shiftKey ? -1 : 1;
        focusables[(current + step + focusables.length) % focusables.length].focus();
    }
</script>

<DropdownMenu
    bind:open={settingsOpen}
    side="top"
    align="end"
    disabled={composerContext.guard.disablesFeature('settings')}
    contentProps={{
        class: 'model-settings-content',
        onCloseAutoFocus: (e) => e.preventDefault()
    }}
>
    {#snippet trigger({props})}
        <ButtonWithTooltip
            tooltip={__('chat.composer.settings.adjustSettingsTooltip')}
            variant="ghost"
            iconLeft={Settings01Icon}
            highlight={settingsOpen}
            {...props}/>
    {/snippet}

    <!-- Keydown only redirects Tab between the child controls; the div itself is not interactive. -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="settings-body" onkeydown={cycleFocusOnTab}>
        <SettingsMenuSystemPrompt/>

        <div class="settings-parameters">
            <SettingsMenuSectionHeader
                title={__('chat.composer.settings.settingsHeading')}
                resetTooltip={__('chat.composer.settings.resetModelSettings')}
                resetDisabled={!modifiedParameters}
                onReset={() => composerContext.modelParameters.reset()}
            />
            <SettingsMenuReasoning/>
            <SettingsMenuSampling/>
        </div>
    </div>
</DropdownMenu>

<style>
    .settings-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-4, calc(0.25rem * 4));
    }

    .settings-parameters {
        display: flex;
        flex-direction: column;
        gap: var(--space-3, calc(0.25rem * 3));
    }

    :global(.dropdown-content.model-settings-content) {
        padding: var(--space-2, calc(0.25rem * 2));
        width: calc(0.25rem * 64);
    }
</style>
