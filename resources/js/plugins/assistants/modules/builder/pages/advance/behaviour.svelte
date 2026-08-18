<script>
import StatusCard from "$lib/plugins/assistants/components/report/StatusCard.svelte";
import BuilderInput from "$lib/plugins/assistants/components/assistantBuilderComponents/BuilderInput.svelte";
import AlertCircleIcon from "$lib/components/ui/icons/iconset/AlertCircleIcon.svelte";
import {assistantOptionsStore} from "$lib/plugins/assistants/stores/AssistantOptionsStore.svelte";
import {ValidationState} from "$lib/plugins/assistants/types/enums/ValidationState";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
const {__} = useTranslator();
</script>



<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.behaviour.title')}</h3>
            <p class="page-description">{__('assistants.builder.behaviour.description')}</p>
        </div>

        <StatusCard
            label={__('assistants.builder.behaviour.warning_system_prompt')}
            icon={AlertCircleIcon}
            type={ValidationState.WARNING}
        />

        <BuilderInput
            type="textarea"
            label={__('assistants.builder.behaviour.input_system_prompt')}
            placeholder={__('assistants.builder.behaviour.input_system_prompt_placeholder')}
            assistantValueKey="systemPrompt"/>

        <BuilderInput
            type="textarea"
            label={__('assistants.builder.behaviour.input_greeting')}
            placeholder={__('assistants.builder.behaviour.input_greeting_placeholder')}
            assistantValueKey="greeting"/>


        <BuilderInput
                name={__('assistants.builder.behaviour.input_starter_prompts_item')}
                type="itemList"
                label={__('assistants.builder.behaviour.input_starter_prompts')}
                render="block"
                assistantValueKey="starterPrompts"/>

        <hr>

        <div class="grid-2">

            <BuilderInput
                    type="select"
                    label={__(assistantOptionsStore.getSetting('formality')?.label ?? 'assistants.settings.formality.label')}
                    hint={__(assistantOptionsStore.getSetting('formality')?.description ?? 'assistants.settings.formality.description')}
                    assistantValueKey="formality"/>

            <BuilderInput
                    type="select"
                    label={__(assistantOptionsStore.getSetting('answer_length')?.label ?? 'assistants.settings.answer_length.label')}
                    hint={__(assistantOptionsStore.getSetting('answer_length')?.description ?? 'assistants.settings.answer_length.description')}
                    assistantValueKey="answerLength"/>

        </div>


    </div>
</div>
