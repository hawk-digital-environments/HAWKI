<script lang="ts">
    import { tick, untrack } from "svelte";
    import Tag from './Tag.svelte';
    import type { AssistantTag as TagType } from '$lib/plugins/assistants/types/assistant/AssistantTag'
    import AddButton from "$lib/plugins/assistants/components/assistantBuilderComponents/tags/AddButton.svelte";
    import InputError from "$lib/plugins/assistants/components/inputError/InputError.svelte";
    import {assistantOptionsStore} from "$lib/plugins/assistants/stores/AssistantOptionsStore.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";

    interface Props {
        id?: string
        label?: string;
        disabled?: boolean;
    }
    const {
        id,
        label,
        disabled = false,
    }: Props = $props();

    const builder = useBuilderContext();

    // eslint-disable-next-line svelte/state_referenced_locally
    let tags = $derived<TagType[]>(builder.draft.tags);
    let tagsEl = $state<HTMLElement | null>(null);
    let highlightedTag = $state<string | null>(null);

    async function addTag(value: string): Promise<void> {
        const normalized: string = value.trim();

        if (!normalized) return;

        const normalizedLower: string = normalized;

        const existing: TagType | undefined = tags.find(
            (tag: TagType) =>
                tag.text === normalizedLower
        );

        if (existing) {
            highlightedTag = existing.text;
            setTimeout(() => {
                highlightedTag = null;
            }, 1200);
            return;
        }

        const newTag:TagType = await assistantOptionsStore.addTag(builder.draft.id, normalized);

        tags = [...tags, newTag];
        builder.set('tags', tags)

        tick().then(() => {
            tagsEl?.scrollTo({
                left: tagsEl.scrollWidth,
                behavior: 'smooth'
            });
        });
    }

    function removeTag(value: string): void {
        tags = tags.filter(t => t.text !== value);
        builder.set('tags', tags)
    }
</script>

<div class="input-container renderBlock">
    {#if label || builder.validator.errorFor('tags')}
        <div class="field-header">
            {#if label}
                <label for={id}>{label}</label>
            {/if}
            <InputError message={builder.validator.errorFor('tags')} />
        </div>
    {/if}

    <div class="tags-container" id={id}>
        <div class="tags" bind:this={tagsEl}>
            {#each tags as tag}
                <Tag value={tag.text}
                     highlighted={highlightedTag === tag.text}
                     onDelete={() => removeTag(tag.text)} />
            {/each}
        </div>
        <AddButton
                suggestions={assistantOptionsStore.tags.map(t=>t.text)}
                onAdd={addTag}
                disabled={disabled} />
    </div>
</div>


<style>
    .tags-container {
        display: flex;
        flex-direction: row;
        align-items: center;
        max-width: 100%;
        gap: var(--space-2);
        padding: var(--space-1) 0;
    }
    .tags:empty {
        display: none;
    }
    .tags {
        display: flex;
        flex-direction: row;
        flex: 0 1 auto;
        min-width: 0;
        align-items: center;
        gap: var(--space-2);
        overflow-x: auto;
        scrollbar-width: none;
    }
    .tags::-webkit-scrollbar {
        display: none;
    }
</style>
