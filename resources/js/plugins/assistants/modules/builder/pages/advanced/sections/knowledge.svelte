<script lang="ts">
    import StatusCard from '$plugins/assistants/components/report/StatusCard.svelte';
    import KnowledgeBases from "$plugins/assistants/modules/builder/components/KnowledgeBases.svelte";
    import FileUpload from "$plugins/assistants/modules/builder/components/FileUpload.svelte";
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {useConfig} from '$lib/app/hooks/useConfig.svelte';
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useStore} from '$lib/app/hooks/useStore.svelte';
    import {isAiToolAvailableFor, knowledgeToolsOf} from "$plugins/core/stores/aiToolStoreData.js";
    import {mockVectorDatabasesEnabled} from "$plugins/assistants/mocks/mockVectorDatabases.svelte.js";
    import MockedVectorDatabases from "$plugins/assistants/mocks/MockedVectorDatabases.svelte";

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props (see core's ChatIndex.svelte), so this interface is intentionally
     * empty.
     */
    interface Props {
    }

    const {}: Props = $props();
    const {__} = useTranslator();
    const config = useConfig();
    const builder = useBuilderContext();
    const modelStore = useStore('ai-models');
    const toolStore = useStore('ai-tools');

    // RAG mode decides where uploaded files go: ingested into the assistant's
    // knowledge-base dataset (rag on) or injected into the conversation context per request (rag off)
    const ragEnabled = $derived(config.rag?.enabled === true);

    // The file-knowledge tool's name, published by the Rag slice through the public config
    const fileKnowledgeTool = $derived(config.rag?.fileKnowledgeTool ?? null);

    // Same model resolution as the model page's conflict panel: the draft
    // stores the provider-side model_id (legacy numeric ids resolve too).
    const currentModel = $derived(modelStore.getOneById(builder.draft.model));

    // Whether a model-resolvable knowledge-base tool exists
    const knowledgeToolAvailable = $derived(
        currentModel !== null
            && knowledgeToolsOf(toolStore.tools).some(t => isAiToolAvailableFor(t, currentModel))
    );

    // Attachable knowledge-base tools: every KB tool the selected model can actually use
    const attachableKnowledgeTools = $derived(
        currentModel
            ? knowledgeToolsOf(toolStore.tools)
                .filter(t => t.name !== fileKnowledgeTool)
                .filter(t => isAiToolAvailableFor(t, currentModel))
            : []
    );

    // With rag on, uploading requires a model whose knowledge-base tool the
    // assistant can use — the files are preassembled into that dataset.
    const uploadDisabled = $derived(ragEnabled && !knowledgeToolAvailable);
    const uploadDisabledHint = $derived(
        ragEnabled && currentModel !== null && !knowledgeToolAvailable
            ? __('assistants.builder.knowledge.upload_disabled_model_not_configured')
            : undefined
    );

</script>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.knowledge.title')}</h3>
            <p class="page-description">{__('assistants.builder.knowledge.description')}</p>
        </div>
        <StatusCard
                label={__('assistants.builder.knowledge.warning_knowledge_sources')}
                icon={AlertCircleIcon}
                type={ValidationState.WARNING}
        />

        {#if ragEnabled && currentModel === null}
            <StatusCard
                    label={__('assistants.builder.knowledge.no_model_selected')}
                    icon={AlertCircleIcon}
                    type={ValidationState.WARNING}
            />
        {/if}
        <FileUpload disabled={uploadDisabled} disabledHint={uploadDisabledHint}/>
        {#if attachableKnowledgeTools.length > 0}
            <KnowledgeBases tools={attachableKnowledgeTools}/>
        {#if ragEnabled && knowledgeToolAvailable}
            <StatusCard
                    label={__('assistants.builder.knowledge.rag_active')}
                    icon={Database01Icon}
                    type={ValidationState.INFO}
            />
        {:else}
            <StatusCard
                label={__('assistants.builder.knowledge.load_files_in_context')}
                icon={Database01Icon}
                type={ValidationState.INFO}
            />
        {/if}
        <FileUpload disabled={uploadDisabled} disabledHint={uploadDisabledHint}/>
    </div>
</div>
