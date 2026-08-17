<script>
    import StatusCard from '$lib/components/generic/report/StatusCard.svelte';
    import KnowledgeBases from "$lib/components/assistant/assistantBuilderComponents/KnowledgeBases.svelte";
    import FileUpload from "$lib/components/assistant/assistantBuilderComponents/FileUpload.svelte";
    import AlertCircleIcon from '$lib/components/generic/icons/iconset/AlertCircleIcon.svelte';
    import {ValidationState} from "$lib/types/enums/ValidationState";
    import {__} from "$lib/utils/translator";
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

        <FileUpload/>
        <KnowledgeBases
        />


    </div>
</div>
