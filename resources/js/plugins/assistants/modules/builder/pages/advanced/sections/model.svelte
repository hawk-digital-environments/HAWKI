<script lang="ts">


    import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
    import ModelSelector from "$plugins/assistants/modules/builder/components/modelSelector/ModelSelector.svelte";
    import ModelToolConflictPanel from "$plugins/assistants/modules/builder/components/modelSelector/ModelToolConflictPanel.svelte";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolSelector from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolSelector.svelte";
    import ToolGroupCard from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolGroupCard.svelte";
    import Tabs, {type TabItem} from "$lib/components/ui/tabs/Tabs.svelte";
    import {samplingPresets} from "$plugins/core/modules/chat/components/composer/samplingPresets.js";
    import Alert from "$lib/components/ui/alert/Alert.svelte";
    import Alert01Icon from "$lib/components/ui/icons/iconset/Alert01Icon.svelte";
    import SlidersHorizontalIcon from "$lib/components/ui/icons/iconset/SlidersHorizontalIcon.svelte";
    import {getMaxOutputTokensLimit} from "$plugins/assistants/modules/builder/contexts/builderUtils.js";
    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props (see core's ChatIndex.svelte), so this interface is intentionally
     * empty.
     */
    interface Props {
    }
    
    const {}: Props = $props();

    const {__} = useTranslator();
    const builder = useBuilderContext();
    const modelStore = useStore('ai-models');

    // The maxTokens slider is bounded by the selected model's own output limit
    // (`limits.max_output_tokens`, e.g. 128000); without a model or a declared
    // limit it falls back to the previous fixed range. `getOneById` also
    // resolves legacy drafts that still store the model's numeric row id.
    const maxOutputTokens = $derived.by(() => {
        const model = modelStore.getOneById(builder.draft.model);
        return model ? getMaxOutputTokensLimit(model) : null;
    });
    const maxTokensMax = $derived(maxOutputTokens ?? 4096);
    // Keep the range valid even for a model with a limit below the default min.
    const maxTokensMin = $derived(Math.min(100, maxTokensMax));

    // Same restriction as the composer: a model without the sampling flag
    // ignores temperature / Top P, so presets hide and the sliders disable.
    const samplingDisabled = $derived.by(() => {
        const model = modelStore.getOneById(builder.draft.model);
        return !!model && !model.flags?.includes('feature-sampling-parameters');
    });

    // The composer's presets, so both surfaces offer the same values.
    const presets = samplingPresets;
    const presetItems: TabItem[] = presets.map(p => ({key: p.key, label: __(p.labelKey)}));

    const activePreset = $derived(
        presets.find(p => builder.draft.temp === p.temp && builder.draft.topP === p.topP)?.key ?? null
    );

    function applyPreset(key: string): void {
        const preset = presets.find(p => p.key === key);
        if (!preset) return;
        builder.set('temp', preset.temp);
        builder.set('topP', preset.topP);
    }
</script>


<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.model.title')}</h3>
            <p class="page-description">{__('assistants.builder.model.description')}</p>
        </div>

        <ModelSelector
            onchange={(modelId) => {builder.setModel(modelId)}}
        />
        <ModelToolConflictPanel/>


<!-- @note: allow model select is left out for now. Needs to be decide if keeping or completely removing the feature in the future.
     also @see: assistant.schema.ts
-->
<!--        <BuilderInput-->
<!--            type="fullWidthToggle"-->
<!--            label={__('assistants.builder.model.input_allow_model_select')}-->
<!--            description={__('assistants.builder.model.input_allow_model_select_description')}-->
<!--            assistantValueKey="allowModelSelect"-->
<!--            disabled={true}-->
<!--        />-->


        <ToolGroupCard
            label={__('assistants.builder.model.advanced_title')}
            description={__('assistants.builder.model.advanced_description')}
            icon={SlidersHorizontalIcon}
        >
            <div class="advanced-fields">
                {#if samplingDisabled}
                    <Alert size="small" tone="warning" description={__('chat.composer.settings.samplingDisabled')} icon={Alert01Icon}/>
                {:else}
                    <Tabs
                        items={presetItems}
                        value={activePreset}
                        onChange={applyPreset}
                        aria-label={__('chat.composer.settings.settingsHeading')}
                        mode="radio"
                    />
                {/if}

                <BuilderInput
                        type="slider"
                        label={__('assistants.builder.model.input_temperature')}
                        min={0}
                        max={2}
                        step={0.1}
                        disabled={samplingDisabled}
                        description={__('assistants.builder.model.input_temperature_description')}
                        hint={__('assistants.builder.model.input_temperature_hint')}
                        assistantValueKey="temp"
                />

                <BuilderInput
                        type="slider"
                        label={__('assistants.builder.model.input_top_p')}
                        min={0}
                        max={1}
                        step={0.05}
                        disabled={samplingDisabled}
                        description={__('assistants.builder.model.input_top_p_description')}
                        hint={__('assistants.builder.model.input_top_p_hint')}
                        assistantValueKey="topP"
                />
                <BuilderInput
                        type="slider"
                        label={__('assistants.builder.model.input_max_tokens')}
                        min={maxTokensMin}
                        max={maxTokensMax}
                        isInteger={true}
                        description={__('assistants.builder.model.input_max_tokens_description')}
                        hint={__('assistants.builder.model.input_max_tokens_hint')}
                        assistantValueKey="maxTokens"
                />
            </div>
        </ToolGroupCard>


        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.tools.title')}</h3>
            <p class="page-description">{__('assistants.builder.tools.description')}</p>
        </div>

        <ToolSelector/>


    </div>
</div>

<style>
    /* The group card's content slot has no layout of its own: space the
       sliders like the page's other fields and set them off from the header. */
    .advanced-fields {
        display: flex;
        flex-direction: column;
        gap: var(--space-6);
        padding-block: var(--space-4) var(--space-2);
    }
</style>
