<script lang="ts">
    import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
    import TagInput from "$plugins/assistants/modules/builder/components/tags/TagInput.svelte";
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import AvatarBuilderPanel from "$plugins/assistants/components/avatarBuilder/AvatarBuilderPanel.svelte";

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
            <h3 class="page-title">{__('assistants.builder.general.title')}</h3>
            <p class="page-description">{__('assistants.builder.general.description')}</p>
        </div>


        <div class="grid-2">
            <BuilderInput
                    type="input"
                    label={__('assistants.builder.general.input_name')}
                    assistantValueKey="name"
            />

            <BuilderInput
                    type="input"
                    label={__('assistants.builder.general.input_handle')}
                    assistantValueKey="handle"/>
        </div>

        <AvatarBuilderPanel/>

        <BuilderInput
                type="input"
                label={__('assistants.builder.general.input_description')}
                assistantValueKey="description"/>

        <BuilderInput
                type="textarea"
                label={__('assistants.builder.general.input_short_description')}
                assistantValueKey="detailDescription"/>


        <div class="grid-2">
            <BuilderInput
                type="select"
                label={__('assistants.builder.general.input_category')}
                assistantValueKey="category"/>

            <BuilderInput
                type="select"
                label={__(assistantOptionsStore.getSetting('language')?.label ?? 'assistants.settings.language.label')}
                hint={__(assistantOptionsStore.getSetting('language')?.description ?? 'assistants.settings.language.description')}
                assistantValueKey="language"/>
        </div>

        <TagInput/>

        <BuilderInput
                type="fullWidthToggle"
                label={__('assistants.builder.general.input_allow_remix')}
                description={__('assistants.builder.general.input_allow_remix_description')}
                assistantValueKey="allowRemix"
                />
    </div>

</div>
