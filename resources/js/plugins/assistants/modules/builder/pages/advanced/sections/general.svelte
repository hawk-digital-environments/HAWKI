<script lang="ts">
    import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
    import TagInput from "$plugins/assistants/modules/builder/components/tags/TagInput.svelte";
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import AvatarBuilderPanel from "$plugins/assistants/components/avatarBuilder/AvatarBuilderPanel.svelte";
    import SharePanel from "$plugins/assistants/modules/builder/components/SharePanel.svelte";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";

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
                    hint={__('assistants.builder.general.input_handle_hint')}
                    assistantValueKey="handle"/>
        </div>

        <AvatarBuilderPanel/>

        <BuilderInput
                type="input"
                label={__('assistants.builder.general.input_description')}
                hint={__('assistants.builder.general.input_description_hint')}
                assistantValueKey="description"/>

        <BuilderInput
                type="textarea"
                label={__('assistants.builder.general.input_short_description')}
                hint={__('assistants.builder.general.input_detail_description_hint')}
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

        <TagInput
                label={__('assistants.builder.general.input_tags')}
                hint={__('assistants.builder.general.input_tags_hint')}/>

        {#if builder.draft.actionPermissions?.listCoAuthors === true}
            <SharePanel
                    list="co-authors"
                    toggleLabel={__('assistants.share.co_authors_toggle')}
                    toggleDescription={__('assistants.share.co_authors_description')}
                    title={__('assistants.share.co_authors_title')}
                    hint={__('assistants.share.co_authors_hint')}
                    emptyText={__('assistants.share.empty')}/>
        {/if}
    </div>

</div>
