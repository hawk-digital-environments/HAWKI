<script lang="ts">


    import BuilderInput from "$plugins/assistants/components/assistantBuilderComponents/BuilderInput.svelte";
    import ModelSelector from "$plugins/assistants/components/modelSelector/ModelSelector.svelte";
    import ModelToolConflictPanel from "$plugins/assistants/components/modelSelector/ModelToolConflictPanel.svelte";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolSelector from "$plugins/assistants/components/assistantBuilderComponents/aiToolComponents/ToolSelector.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

    const {__} = useTranslator();
    const builder = useBuilderContext();
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

        <BuilderInput
            type="fullWidthToggle"
            label={__('assistants.builder.model.input_allow_model_select')}
            description={__('assistants.builder.model.input_allow_model_select_description')}
            assistantValueKey="allowModelSelect"
            disabled={true}
        />


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
                min={100}
                max={4096}
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
