<!--
  @component One of the assistant's people lists (exclusive audience or
  co-authors) in the builder. Loads the list once and saves every add or
  remove straight away: the lists are not content, so they never ride the
  draft's autosave or the review's frozen version. With `toggleLabel` the
  picker sits behind a switch, and switching it off clears the list.
-->
<script lang="ts">
    import { untrack } from 'svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte.js';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import { growTransition } from '$lib/utils/transitions/growTransition';
    import FullWidthToggle from '$plugins/assistants/components/toggle/FullWidthToggle.svelte';
    import PeoplePicker from '$plugins/assistants/components/peoplePicker/PeoplePicker.svelte';
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {
        listAssistantShares,
        syncAssistantShares,
        type AssistantShareEntry,
        type AssistantShareList
    } from '$plugins/assistants/api/resources/assistantsClient';
    import { ApiError } from '$plugins/assistants/api/errors';

    interface Props {
        list: AssistantShareList;
        title: string;
        hint: string;
        emptyText: string;
        /** Puts the picker behind a switch with this label. */
        toggleLabel?: string;
        toggleDescription?: string;
    }

    const { list, title, hint, emptyText, toggleLabel, toggleDescription }: Props = $props();

    const { __ } = useTranslator();
    const toast = useToastContext();
    const builder = useBuilderContext();

    const assistantId = $derived(builder.draft.id);
    const excludeIds = $derived(builder.draft.creator?.id ? [Number(builder.draft.creator.id)] : []);

    let people = $state.raw<AssistantShareEntry[]>([]);
    let loaded = $state(false);
    let enabled = $state(false);

    $effect(() => {
        const id = assistantId;
        if (id) untrack(() => void load(id));
    });

    async function load(id: string): Promise<void> {
        loaded = false;
        try {
            people = await listAssistantShares(id, list);
            enabled = people.length > 0;
        } catch (err) {
            toast.error(`${__('assistants.share.load_failed')} ${ApiError.from(err).userMessage}`);
        } finally {
            loaded = true;
        }
    }

    /** Guards against a slow earlier save overwriting a newer one's answer. */
    let saveSeq = 0;

    /** Shows `next` right away and adopts the server's list once it answers. */
    async function save(next: AssistantShareEntry[]): Promise<void> {
        const id = assistantId;
        if (!id) return;
        const previous = people;
        const seq = ++saveSeq;
        people = next;
        try {
            const saved = await syncAssistantShares(id, list, next.map((person) => person.userId));
            if (seq === saveSeq) people = saved;
        } catch (err) {
            if (seq === saveSeq) people = previous;
            toast.error(`${__('assistants.share.save_failed')} ${ApiError.from(err).userMessage}`);
        }
    }

    function onToggle(value: boolean): void {
        enabled = value;
        if (!value && people.length > 0) void save([]);
    }
</script>

<Loader active={!loaded} label={__('assistants.browser.loading')}>
    <div class="share-panel">
        {#if toggleLabel}
            <FullWidthToggle label={toggleLabel} description={toggleDescription} defaultValue={enabled} onchange={onToggle} />
        {/if}

        {#if enabled || !toggleLabel}
            <div class="share-body" transition:growTransition>
                <div class="share-header">
                    <p class="u-label">{title}</p>
                    <p class="share-hint">{hint}</p>
                </div>
                <PeoplePicker
                    {people}
                    {excludeIds}
                    {emptyText}
                    onadd={(person) => void save([person, ...people])}
                    onremove={(userId) => void save(people.filter((person) => person.userId !== userId))}
                />
            </div>
        {/if}
    </div>
</Loader>

<style>
    .share-panel,
    .share-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    .share-body {
        padding: var(--space-4);
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
    }

    .share-header {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    .share-header .u-label {
        margin: 0;
        font-weight: var(--font-weight-semibold);
    }

    .share-hint {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
