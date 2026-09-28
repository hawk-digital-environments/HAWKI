<script lang="ts">
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import KnowledgeBases from "$plugins/assistants/modules/builder/components/KnowledgeBases.svelte";
    import FileUpload from "$plugins/assistants/modules/builder/components/FileUpload.svelte";
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {useConfig} from '$lib/app/hooks/useConfig.svelte';
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useStore} from '$lib/app/hooks/useStore.svelte';
    import {isAiToolAvailableFor, knowledgeToolsOf} from "$plugins/core/stores/aiToolStoreData.js";
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
    // own knowledge-base dataset (rag on) or injected into the conversation
    // context per request (rag off). Ingestion is an upload-time workflow and
    // model-independent — a missing model never blocks uploads (FileUpload
    // gates only the incapable-model case, see `knowledgeUploader.svelte.ts`).

    // The file-knowledge tool's name, published by the Rag slice through the public config
    const fileKnowledgeTool = $derived(config.rag?.fileKnowledgeTool ?? null);

    // Same model resolution as the model page's conflict panel: the draft
    // stores the provider-side model_id (legacy numeric ids resolve too).
    const currentModel = $derived(modelStore.getOneById(builder.draft.model));

    // Attachable knowledge-base tools: every KB tool the selected model can actually use
    const attachableKnowledgeTools = $derived(
        currentModel
            ? knowledgeToolsOf(toolStore.tools)
                .filter(t => t.name !== fileKnowledgeTool)
                .filter(t => isAiToolAvailableFor(t, currentModel))
            : []
    );
</script>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.knowledge.title')}</h3>
            <p class="page-description">{__('assistants.builder.knowledge.description')}</p>
        </div>

        <Alert
                size="small"
                description={__('assistants.builder.knowledge.warning_knowledge_sources')}
                icon={AlertCircleIcon}
                tone="neutral"
        />

        <FileUpload/>
        {#if attachableKnowledgeTools.length > 0}
            <KnowledgeBases tools={attachableKnowledgeTools}/>
        {/if}

    </div>
</div>
