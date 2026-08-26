<!--
  @component Trigger + panel for the composer's model settings. Shown as a
  `Popover` on `bpMd`-and-bigger viewports, a `BottomSheet` below that (via
  `Breakpoint`) — the same `settingsBody` snippet is rendered into both.

  The panel is composed of three sections, each owning its own state and
  styles and talking to `ComposerContext` directly:
  - `SettingsMenuSystemPrompt` — preview, reset and dialog for the system prompt.
  - `SettingsMenuReasoning` — reasoning effort row (hidden for models without
    adjustable reasoning).
  - `SettingsMenuSampling` — temperature/Top P presets and sliders.

  The "Settings" heading's reset button restores the model's default
  parameters via `modelParameters.reset()`. Disabled as a whole when
  `composerContext.guard.disablesFeature('settings')` is true (e.g. during
  edit/regen mode). Takes no props.
-->
<script lang="ts">
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
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
</script>

{#snippet settingsBody()}
    <div class="settings-body">
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
{/snippet}

<Breakpoint>
    {#snippet bpSmallerThanMd()}
        <ButtonWithTooltip
            tooltip={__('chat.composer.settings.adjustSettingsTooltip')}
            variant="ghost"
            iconLeft={Settings01Icon}
            disabled={composerContext.guard.disablesFeature('settings')}
            highlight={settingsOpen}
            onclick={() => (settingsOpen = true)}
        />
        <BottomSheet bind:open={settingsOpen} title={__('chat.composer.settings.settingsTitle')}>
            {@render settingsBody()}
        </BottomSheet>
    {/snippet}
    {#snippet children()}
        <Popover
            align="end"
            sideOffset={4}
            contentProps={{
                class: 'model-settings-content',
                onCloseAutoFocus: (e) => e.preventDefault()
            }}
        >
            {#snippet children({props})}
                <ButtonWithTooltip
                    tooltip={__('chat.composer.settings.adjustSettingsTooltip')}
                    variant="ghost"
                    iconLeft={Settings01Icon}
                    disabled={composerContext.guard.disablesFeature('settings')}
                    highlight={props['data-state']}
                    {...props}/>
            {/snippet}
            {#snippet popover()}
                {@render settingsBody()}
            {/snippet}
        </Popover>
    {/snippet}
</Breakpoint>

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

    :global(.model-settings-content) {
        padding: var(--space-2, calc(0.25rem * 2));
        width: calc(0.25rem * 64);
    }
</style>
