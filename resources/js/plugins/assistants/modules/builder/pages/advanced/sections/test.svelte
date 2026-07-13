<script lang="ts">

    import Chatbox from "$plugins/assistants/components/testChat";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
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

    // The builder owns the assistant under test: its live `draft` (not
    // `baseline`) so the test chat reflects unsaved edits immediately.
    const builder = useBuilderContext();
</script>


<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.test.title')}</h3>
            <p class="page-description">{__('assistants.builder.test.description')}</p>
        </div>

        <Chatbox assistant={builder.draft}/>



    </div>
</div>
