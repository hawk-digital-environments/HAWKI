<script lang="ts">


    import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
    import ModelSelector from "$plugins/assistants/modules/builder/components/modelSelector/ModelSelector.svelte";
    import ModelToolConflictPanel from "$plugins/assistants/modules/builder/components/modelSelector/ModelToolConflictPanel.svelte";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolSelector from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolSelector.svelte";
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
    // limit it falls back to the previous fixed range.
    const maxOutputTokens = $derived.by(() => {
        const model = modelStore.models.find(m => m.id === builder.draft.model);
        return model ? getMaxOutputTokensLimit(model) : null;
    });
    const maxTokensMax = $derived(maxOutputTokens ?? 4096);
    // Keep the range valid even for a model with a limit below the default min.
    const maxTokensMin = $derived(Math.min(100, maxTokensMax));
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


        <BuilderInput
                type="slider"
                label={__('assistants.builder.model.input_temperature')}
                min={0}
                max={1}
                description={__('assistants.builder.model.input_temperature_description')}
                hint={__('assistants.builder.model.input_temperature_hint')}
                assistantValueKey="temp"
        />

        <BuilderInput
                type="slider"
                label={__('assistants.builder.model.input_top_p')}
                min={0}
                max={1}
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


        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.tools.title')}</h3>
            <p class="page-description">{__('assistants.builder.tools.description')}</p>
        </div>

        <ToolSelector/>


    </div>
</div>
