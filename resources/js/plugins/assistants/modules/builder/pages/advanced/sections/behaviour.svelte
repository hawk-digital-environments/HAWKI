<script lang="ts">
import StatusCard from "$plugins/assistants/components/report/StatusCard.svelte";
import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
import AlertCircleIcon from "$lib/components/ui/icons/iconset/AlertCircleIcon.svelte";
import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";
import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";
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
                type="itemList"
                label={__('assistants.builder.behaviour.input_starter_prompts')}
                addItemLabel={__('assistants.builder.behaviour.input_starter_prompts_add')}
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
                    label={__(assistantOptionsStore.getSetting('answer_style')?.label ?? 'assistants.settings.answer_style.label')}
                    hint={__(assistantOptionsStore.getSetting('answer_style')?.description ?? 'assistants.settings.answer_style.description')}
                    assistantValueKey="answerStyle"/>

        </div>


    </div>
</div>
