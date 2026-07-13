<script lang="ts">
    import StatusCard from '$plugins/assistants/components/report/StatusCard.svelte';
    import KnowledgeBases from "$plugins/assistants/modules/builder/components/KnowledgeBases.svelte";
    import FileUpload from "$plugins/assistants/modules/builder/components/FileUpload.svelte";
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
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
