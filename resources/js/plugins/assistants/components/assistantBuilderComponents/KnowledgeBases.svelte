<script lang="ts">
import FullWidthToggle from "$lib/plugins/assistants/components/toggle/FullWidthToggle.svelte";
import Database01Icon from "$lib/components/ui/icons/iconset/Database01Icon.svelte";
import { assistantBuilderStore } from '$lib/plugins/assistants/stores/AssistantBuilderStore.svelte.js';
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

const {__} = useTranslator()

const raw= [
    {
        "id": 1,
        "name": "Hochschul-Wissensbasis",
        "documents": 12450
    },
    {
        "id": 2,
        "name": "Fachbereichsbibliothek",
        "documents": 3280
    }
];

let {
    render = 'block'
} = $props <{
    render?: 'block' | 'inline';
}>();

interface KnowledgeBase {
    id: number;
    name: string;
    documents: number;
}
const kdb = (raw as KnowledgeBase[]).map(db => ({ ...db, _toggleId: db.id }));


let kdbValues = $derived((assistantBuilderStore.draft.knowledgeBases ?? []) as string[]);

function onToggleInput(kdbId: number, value: boolean) {
    const id = String(kdbId);
    const current = assistantBuilderStore.draft.knowledgeBases ?? [];

    assistantBuilderStore.set('knowledgeBases', value
        ? [...new Set([...current, id])]
        : current.filter(existing => existing !== id));
}

</script>



<div class="input-container"
     class:renderBlock={render === 'block'}
     class:renderInline={render === 'inline'}
>
    <label class="label" for="vdb-list">{__('assistants.builder.knowledge.input_vector_databases')}</label>

    <div class="vdb-list" id="vdb-list">
        {#each kdb as db (db.id)}
            <FullWidthToggle
                    icon={Database01Icon}
                    label={db.name}
                    description={db.documents + " " + __('assistants.builder.knowledge.documents_unit')}
                    defaultValue={kdbValues.includes(String(db.id))}
                    onchange={(value) => onToggleInput(db.id, value)}
            />
        {/each}
    </div>

</div>


<style>
.vdb-list{
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

</style>
