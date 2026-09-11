<script lang="ts">
import FullWidthToggle from "$plugins/assistants/components/toggle/FullWidthToggle.svelte";
import Database01Icon from "$lib/components/ui/icons/iconset/Database01Icon.svelte";
import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
import type {ExtendedAiTool} from "$plugins/core/stores/aiToolStoreData.js";

const {__} = useTranslator()
const builder = useBuilderContext();

let {
    /** Knowledge-database tools assignable for the currently selected model —
     *  derived by the knowledge page (see `knowledgeToolsOf`). */
    tools,
    render = 'block'
} = $props <{
    tools: ExtendedAiTool[];
    render?: 'block' | 'inline';
}>();

// Ids of the tools currently attached to the draft — drives initial toggle state.
const selectedIds = $derived(
    new Set((builder.draft.aiTools ?? []).map(t => t.id))
);

// `FullWidthToggle` is uncontrolled (defaultValue is read once), so the list
// must not render before the builder's async init() has installed the draft —
// otherwise toggles for already-attached tools would initialize as off.
const ready = $derived(builder.draft.id !== null);

// Attaching/detaching works exactly like the model page's ToolSelector: the
// toggle merges the tool into `draft.aiTools`, and the builder's autosave
// PATCHes it as the assistant's `ai_tools` relationship.
function onchange(tool: ExtendedAiTool, active: boolean) {
    const current = builder.draft.aiTools ?? [];
    const next = active
        ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
        : current.filter(t => t.id !== tool.id);
    builder.set('aiTools', next);
}

</script>



<div class="input-container"
     class:renderBlock={render === 'block'}
     class:renderInline={render === 'inline'}
>
    <label for="kdb-list">{__('assistants.builder.knowledge.input_knowledge_databases')}</label>

    {#if ready}
        <div class="kdb-list" id="kdb-list">
            {#each tools as tool (tool.id)}
                <FullWidthToggle
                        icon={Database01Icon}
                        label={tool.displayName}
                        description={tool.description}
                        defaultValue={selectedIds.has(tool.id)}
                        onchange={(value) => onchange(tool, value)}
                />
            {/each}
        </div>
    {/if}

</div>


<style>
    .kdb-list{
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }

</style>
