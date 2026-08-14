<script lang="ts">

import {aiModelsStore} from "$lib/stores/AiModelsStore.svelte.js";
import type {AiModel} from "$lib/types/aiModel/AiModel";
import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte.js";

const {
    disabled = false,
    onchange,
} = $props<{
    disabled?: boolean;
    onchange?: (value: string) => void;
}>();

</script>



<div class="input-container renderBlock">
    <label for="modelSelector">Empfohlenes Modell</label>

    <select
            id='modelSelector'
            {disabled}
            onchange={(e) => onchange?.(e.currentTarget.value)}
    >
        {#if !assistantBuilderStore.draft.model}
            <option disabled selected value>Select a Model</option>
        {/if}
        {#each aiModelsStore.models as model}
            {#if assistantBuilderStore.draft.model &&
                model.modelId === assistantBuilderStore.draft.model}
                <option value={model.modelId} selected>{model.label}</option>
            {:else}
                <option value={model.modelId}>{model.label}</option>
            {/if}
        {/each}
    </select>

</div>


<style>

    select {
        --select-border: var(--color-border);

        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border-radius: var(--corner-md);
        border: var(--border);
        border-color: var(--select-border);
        background-color: transparent;
        font-size: var(--font-size-sm);
        font-family: inherit;
        color: var(--color-text);
        box-shadow: var(--elevation-1);
        transition:
                border-color var(--transition-fast),
                box-shadow var(--transition-fast);
    }

    select:focus-visible {
        outline: none;
        --select-border: var(--color-focus-ring);
        box-shadow: 0 0 0 1px var(--color-focus-ring);
    }

    select:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
</style>