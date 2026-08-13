<!--
  @component Trigger + panel for the system prompt preview and model generation
  parameters (temperature, Top P). Shown as a `Popover` on `bpMd`-and-bigger
  viewports, a `BottomSheet` below that (via `Breakpoint`) — the same
  `settingsBody` snippet is rendered into both.

  - Temperature/Top P are editable via sliders or one of three presets
    (`creative`/`balanced`/`precise`); the active tab is derived by matching
    the current values against each preset (`modelParameters.intersects`).
    Sliders/tabs are disabled, with an explanatory `Alert`, when the current
    model lacks the `'feature-sampling-parameters'` flag.
  - Clicking the system-prompt preview opens `SystemPromptDialog` for full
    editing; a reset button (enabled only when changed) restores
    `composerContext.systemPrompt` to the `system-prompts` store's `'default'`
    entry. Same reset pattern for the sampling parameters via `modelParameters.reset()`.

  Disabled as a whole when `composerContext.guard.disablesFeature('settings')`
  is true (e.g. during edit/regen mode). Takes no props — reads/writes
  `ComposerContext` directly.
-->
<script lang="ts">
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import Tabs, {type TabItem} from '$lib/components/ui/tabs/Tabs.svelte';
    import Slider from '$lib/components/ui/slider/Slider.svelte';
    import Txt from '$lib/components/ui/Txt.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import SystemPromptDialog from '$plugins/core/modules/chat/components/composer/SystemPromptDialog.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
    import UndoIcon from '$lib/components/ui/icons/iconset/UndoIcon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import Alert01Icon from '$lib/components/ui/icons/iconset/Alert01Icon.svelte';

    const composerContext = useComposerContext();
    const systemPromptStore = useStore('system-prompts');
    const {__} = useTranslator();

    let settingsOpen = $state(false);
    let systemPromptOpen = $state(false);

    type Preset = 'creative' | 'balanced' | 'precise' | null;

    const presets: { key: Preset; label: string; temp: number; topP: number }[] = [
        {key: 'creative', label: __('chat.composer.settings.presetCreative'), temp: 1.4, topP: 0.95},
        {key: 'balanced', label: __('chat.composer.settings.presetBalanced'), temp: 0.7, topP: 0.9},
        {key: 'precise', label: __('chat.composer.settings.presetPrecise'), temp: 0.2, topP: 0.5}
    ];

    const activePreset = $derived<Preset>(
        presets.find(p => composerContext.modelParameters.intersects({temperature: p.temp, top_p: p.topP}))?.key ?? null
    );
    const model = $derived.by(() => composerContext.model);
    const tabItems: TabItem[] = presets.map(p => ({key: p.key as string, label: p.label}));
    const modifiedParameters = $derived.by(() => composerContext.modelParameters.isModified);
    const defaultSystemPrompt = $derived.by(() => systemPromptStore.getPromptByType('default')?.prompt ?? '');
    const hasCustomSystemPrompt = $derived.by(() => composerContext.systemPrompt !== defaultSystemPrompt);

    function handlePresetChange(key: string) {
        const preset = presets.find(p => p.key === key);
        if (preset) {
            composerContext.modelParameters.set('temperature', preset.temp);
            composerContext.modelParameters.set('top_p', preset.topP);
        }
    }

    function handleReset() {
        composerContext.modelParameters.reset();
    }

    function handleSystemPromptReset() {
        composerContext.systemPrompt = defaultSystemPrompt;
    }

    const samplingDisabled = $derived.by(() => !model.current.flags?.includes("feature-sampling-parameters"));
</script>

{#snippet settingsBody()}
    <div class="settings-body">
        <div class="system-prompt-section">
            <div class="system-prompt-header">
                <h4 class="settings-heading">{__('chat.composer.settings.systemPromptHeading')}</h4>
                <ButtonWithTooltip
                    tooltip={__('chat.composer.settings.resetSystemPrompt')}
                    iconLeft={UndoIcon}
                    onclick={handleSystemPromptReset}
                    variant="iconGhost"
                    disabled={!hasCustomSystemPrompt}
                    tabindex={hasCustomSystemPrompt ? 0 : -1}
                    class="system-prompt-reset-button"
                ></ButtonWithTooltip>
            </div>
            <button type="button" class="system-prompt-preview" onclick={() => systemPromptOpen = true}>
                <PencilEdit01Icon size={14} class="system-prompt-icon"/>
                <span class="system-prompt-text">
                        {composerContext.systemPrompt?.trim() ? composerContext.systemPrompt : __('chat.composer.settings.noSystemPrompt')}
                    </span>
            </button>
        </div>

        <div class="settings-parameters-wrapper">
            <div class="settings-parameters-header">
                <h4 class="settings-heading">{__('chat.composer.settings.settingsHeading')}</h4>
                <ButtonWithTooltip
                    tooltip={__('chat.composer.settings.resetModelSettings')}
                    iconLeft={UndoIcon}
                    onclick={handleReset}
                    variant="iconGhost"
                    disabled={!modifiedParameters}
                    tabindex={modifiedParameters ? 0 : -1}
                    class="model-settings-reset-button"
                ></ButtonWithTooltip>
            </div>
            {#if samplingDisabled}
                <Alert description={__("chat.composer.settings.samplingDisabled")} icon={Alert01Icon} size="small" />
            {/if}
            <Tabs
                items={tabItems}
                value={activePreset}
                onChange={handlePresetChange}
                aria-label={__('chat.composer.settings.settingsHeading')}
                disabled={samplingDisabled}
            />
        </div>

        <div class="sliders-section">
            <div class="slider-group">
                <div class="slider-header">
                    <Txt size="xs">
                        {__('chat.composer.settings.temperature')}
                        <InfoPopover info={__('chat.composer.settings.temperatureInfo')} disabled={samplingDisabled} />
                    </Txt>
                    <Txt size="xs">{composerContext.modelParameters.get('temperature').toFixed(1)}</Txt>
                </div>
                <Slider
                    aria-label={__('chat.composer.settings.temperatureAriaLabel')}
                    value={composerContext.modelParameters.get('temperature')}
                    onValueChange={(v: number) => composerContext.modelParameters.set('temperature', v)}
                    min={0}
                    max={2}
                    step={0.1}
                    disabled={samplingDisabled}
                />
            </div>

            <div class="slider-group">
                <div class="slider-header">
                    <Txt size="xs">
                        {__('chat.composer.settings.topP')}
                        <InfoPopover info={__('chat.composer.settings.topPInfo')} disabled={samplingDisabled} />
                    </Txt>
                    <Txt size="xs">{composerContext.modelParameters.get('top_p').toFixed(2)}</Txt>
                </div>
                <Slider
                    aria-label={__('chat.composer.settings.topPAriaLabel')}
                    value={composerContext.modelParameters.get('top_p')}
                    onValueChange={(v: number) => composerContext.modelParameters.set('top_p', v)}
                    min={0}
                    max={1}
                    step={0.05}
                    disabled={samplingDisabled}
                />
            </div>
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

<SystemPromptDialog
    bind:open={systemPromptOpen}
    value={composerContext.systemPrompt ?? ''}
    onChange={(newPrompt) => { composerContext.systemPrompt = newPrompt; }}
/>

<style>

    /* ── Content layout ───────────────────────────────────────────────── */

    .settings-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-4, calc(0.25rem * 4));
    }

    :global(.model-settings-content) {
        padding: var(--space-2, calc(0.25rem * 2));
        width: calc(0.25rem * 64);
    }

    /* ── Heading ──────────────────────────────────────────────────────── */

    .settings-heading {
        margin: 0 0 var(--space-3, calc(0.25rem * 3)) 0;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    .settings-parameters-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));

        .settings-heading {
            margin-bottom: 0;
        }
    }

    .settings-parameters-wrapper {
        display: flex;
        flex-direction: column;
        gap: var(--space-3, calc(0.25rem * 3));
    }

    :global(.system-prompt-reset-button),
    :global(.model-settings-reset-button) {
        width: auto;
        height: auto;
    }

    /* ── Sliders ──────────────────────────────────────────────────────── */

    .sliders-section {
        display: flex;
        flex-direction: column;
        gap: var(--space-3, calc(0.25rem * 3));
    }

    .slider-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .slider-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* ── System prompt ────────────────────────────────────────────────── */

    .system-prompt-section {
        display: flex;
        flex-direction: column;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .system-prompt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        .settings-heading {
            margin-bottom: 0;
        }
    }

    .system-prompt-preview {
        display: flex;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        width: 100%;
        height: calc((var(--font-size-xs) * var(--line-height-tight)) + var(--space-4));
        box-sizing: border-box;
        padding: 0 var(--space-2, calc(0.25rem * 2));
        border: var(--border);
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text);
        cursor: pointer;
        text-align: left;
        transition: border-color var(--duration-fast, 150ms) var(--easing-default);
    }

    .system-prompt-preview:hover {
        border-color: var(--color-text-muted);
    }

    :global(.system-prompt-icon) {
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .system-prompt-text {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-tight);
    }
</style>
