<script>
    import BuilderInput from "$lib/plugins/assistants/components/assistantBuilderComponents/BuilderInput.svelte";
    import TagInput from "$lib/plugins/assistants/components/assistantBuilderComponents/tags/TagInput.svelte";
    import {assistantOptionsStore} from "$lib/plugins/assistants/stores/AssistantOptionsStore.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import AvatarBuilderPanel from "$plugins/assistants/components/avatarBuilder/AvatarBuilderPanel.svelte";

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
