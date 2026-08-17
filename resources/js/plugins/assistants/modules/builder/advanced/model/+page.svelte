<script lang="ts">


    import BuilderInput from "$lib/components/assistant/assistantBuilderComponents/BuilderInput.svelte";
    import ModelSelector from "$lib/components/modelSelector/ModelSelector.svelte";
    import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte.js";
    import ToolSelector from "$lib/components/assistant/assistantBuilderComponents/aiToolComponents/ToolSelector.svelte";
    import {__} from "$lib/utils/translator";



</script>


<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.model.title')}</h3>
            <p class="page-description">{__('assistants.builder.model.description')}</p>
        </div>

        <ModelSelector
            onchange={(modelId) => {assistantBuilderStore.setModel(modelId)}}
        />

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

<!--        <ReportPanel-->
<!--                icon="build"-->
<!--                label={1 + " Tools ausgewählt"}-->
<!--        />-->

        <ToolSelector/>


    </div>
</div>
