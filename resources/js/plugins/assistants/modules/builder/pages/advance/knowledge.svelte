<script>
    import StatusCard from '$lib/plugins/assistants/components/report/StatusCard.svelte';
    import KnowledgeBases from "$lib/plugins/assistants/components/assistantBuilderComponents/KnowledgeBases.svelte";
    import FileUpload from "$lib/plugins/assistants/components/assistantBuilderComponents/FileUpload.svelte";
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import {ValidationState} from "$lib/plugins/assistants/types/enums/ValidationState";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
const {__} = useTranslator();</script>

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
