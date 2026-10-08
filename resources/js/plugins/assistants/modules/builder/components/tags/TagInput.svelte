<script lang="ts">
    import { untrack } from "svelte";
    import Tag from '$plugins/assistants/components/tags/Tag.svelte';
    import type { AssistantTag as TagType } from '$plugins/assistants/types/assistant/AssistantTag'
    import AddButton from "$plugins/assistants/components/tags/AddButton.svelte";
    import InputError from "$plugins/assistants/components/inputError/InputError.svelte";
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    import {ApiError} from "$plugins/assistants/api/errors";
    import InfoPopover from "$lib/components/ui/popover/InfoPopover.svelte";
    import AiFillReveal from "$plugins/assistants/modules/builder/components/AiFillReveal.svelte";

    interface Props {
        id?: string
        label?: string;
        /** Explanation shown in an info popover next to the label. */
        hint?: string;
        disabled?: boolean;
    }
    const {
        id,
        label,
        hint,
        disabled = false,
    }: Props = $props();

    const builder = useBuilderContext();
    const toast = useToastContext();

    // eslint-disable-next-line svelte/state_referenced_locally
    let tags = $derived<TagType[]>(builder.draft.tags);
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

        builder.validator.clearError('tags');

        try {
            const newTag: TagType = await assistantOptionsStore.addTag(normalized);

            tags = [...tags, newTag];
            builder.set('tags', tags)
        } catch (err) {
            // A unique-name conflict (or any other field-scoped validation
            // failure) belongs on the field itself; anything else (dropped
            // connection, server error) has nowhere else to surface but a toast.
            const apiErr = ApiError.from(err);
            if (apiErr.isValidation) {
                builder.validator.recordFieldError('tags', apiErr.fieldErrors[0]?.message ?? apiErr.userMessage);
            } else {
                toast.error(apiErr.userMessage);
            }
        }
    }

    function removeTag(value: string): void {
        tags = tags.filter(t => t.text !== value);
        builder.set('tags', tags)
    }
</script>

<div class="input-container renderBlock">
    {#if label}
        <div class="field-header">
            {#if label}
                <label for={id}>{label}</label>
            {/if}
            {#if label && hint}
                <InfoPopover {label} info={hint}/>
            {/if}
        </div>
    {/if}

    <!-- Blue reveal when the AI guide fills the tags. -->
    <AiFillReveal field="tags">
    <div class="tags-container" id={id}>
        <div class="tags">
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
    </AiFillReveal>
    <InputError message={builder.validator.errorFor('tags')} />
</div>


<style>
    /* Tags wrap into rows; "Add tag" follows the last tag. */
    .tags-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        max-width: 100%;
        gap: var(--space-2);
        padding: var(--space-1) 0;
    }
    .tags {
        display: contents;
    }
</style>
