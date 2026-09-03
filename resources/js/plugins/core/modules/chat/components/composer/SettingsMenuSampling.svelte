<!--
  @component Sampling parameter controls of the `SettingsMenu` (temperature and
  Top P). Offers three presets as `Tabs` (`creative`/`balanced`/`precise`; the
  active one is derived by matching the current values via
  `modelParameters.intersects`) and, behind an "advanced settings" expander,
  the raw sliders. When the current model lacks the
  `'feature-sampling-parameters'` flag the presets are hidden and the sliders
  are disabled with an explanatory `Alert`. Takes no props — reads/writes
  `ComposerContext` directly.
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import Tabs, {type TabItem} from '$lib/components/ui/tabs/Tabs.svelte';
    import Slider from '$lib/components/ui/slider/Slider.svelte';
    import Txt from '$lib/components/ui/Txt.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import Alert01Icon from '$lib/components/ui/icons/iconset/Alert01Icon.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {slide} from 'svelte/transition';
    import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    type Preset = 'creative' | 'balanced' | 'precise' | null;

    const presets: { key: Preset; label: string; temp: number; topP: number }[] = [
        {key: 'creative', label: __('chat.composer.settings.presetCreative'), temp: 1.4, topP: 0.95},
        {key: 'balanced', label: __('chat.composer.settings.presetBalanced'), temp: 0.7, topP: 0.9},
        {key: 'precise', label: __('chat.composer.settings.presetPrecise'), temp: 0.2, topP: 0.5}
    ];
    const tabItems: TabItem[] = presets.map(p => ({key: p.key as string, label: p.label}));

    const activePreset = $derived<Preset>(
        presets.find(p => composerContext.modelParameters.intersects({temperature: p.temp, top_p: p.topP}))?.key ?? null
    );
    const samplingDisabled = $derived.by(() => !composerContext.model.current.flags?.includes('feature-sampling-parameters'));

    let advancedOpen = $state(false);

    function handlePresetChange(key: string) {
        const preset = presets.find(p => p.key === key);
        if (preset) {
            composerContext.modelParameters.set('temperature', preset.temp);
            composerContext.modelParameters.set('top_p', preset.topP);
        }
    }
</script>

{#if !samplingDisabled}
    <Tabs
        items={tabItems}
        value={activePreset}
        onChange={handlePresetChange}
        aria-label={__('chat.composer.settings.settingsHeading')}
        mode="radio"
    />
{/if}

<button
    type="button"
    class="advanced-toggle"
    aria-expanded={advancedOpen}
    aria-controls="model-settings-advanced"
    onclick={() => (advancedOpen = !advancedOpen)}
>
    <span>{__('chat.composer.settings.advancedSettings')}</span>
    <ChevronDownIcon size={14} class={advancedOpen ? 'advanced-toggle-chevron is-open' : 'advanced-toggle-chevron'}/>
</button>

{#if advancedOpen}
    <div class="sliders-section" id="model-settings-advanced" transition:slide={{duration: motionDuration(150)}}>
        {#if samplingDisabled}
            <Alert description={__('chat.composer.settings.samplingDisabled')} icon={Alert01Icon} size="small"/>
        {/if}

        <div class="slider-group">
            <div class="slider-header">
                <Txt size="xs">
                    {__('chat.composer.settings.temperature')}
                    <InfoPopover
                        label={__('chat.composer.settings.temperature')}
                        info={__('chat.composer.settings.temperatureInfo')}
                        disabled={samplingDisabled}/>
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
                    <InfoPopover
                        label={__('chat.composer.settings.topP')}
                        info={__('chat.composer.settings.topPInfo')}
                        disabled={samplingDisabled}/>
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
{/if}

<style>
    .advanced-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
        width: 100%;
        box-sizing: border-box;
        padding: var(--space-1_5) var(--space-2, calc(0.25rem * 2));
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        cursor: pointer;
        text-align: left;
        transition: background-color var(--duration-fast, 150ms), color var(--duration-fast, 150ms);
    }

    .advanced-toggle:hover,
    .advanced-toggle[aria-expanded="true"] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    :global(.advanced-toggle-chevron) {
        transition: rotate var(--duration-fast, 150ms) var(--easing-default);
    }

    :global(.advanced-toggle-chevron.is-open) {
        rotate: 180deg;
    }

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
</style>
