<script lang="ts">

    import { tick, onMount } from 'svelte';
    import Add01Icon from "$lib/components/ui/icons/iconset/Add01Icon.svelte";
    import Tick02Icon from "$lib/components/ui/icons/iconset/Tick02Icon.svelte";
        const {__} = useTranslator();

    let {
        suggestions = [],
        disabled = false,
        onAdd,
    } = $props<{
        suggestions?: string[];
        disabled?: boolean;
        onAdd?: (value: string) => void;
    }>();

    let editMode = $state<boolean>(false);

    let inputEl = $state<HTMLInputElement | null>(null);
    let containerEl = $state<HTMLDivElement | null>(null);

    let value = $state<string>('');

    let autoFillVals = $derived<string[]>(
        !value.trim()
            ? []
            : suggestions
                .map((tag: string) => ({
                    tag,
                    score: tag.toLowerCase().startsWith(value.toLowerCase())
                        ? 0
                        : tag.toLowerCase().includes(value.toLowerCase())
                            ? 1
                            : 999
                }))
                .filter(
                    (x: { tag: string; score: number }) =>
                        x.score < 999
                )
                .sort(
                    (
                        a: { tag: string; score: number },
                        b: { tag: string; score: number }
                    ) => a.score - b.score
                )
                .slice(0, 5)
                .map(
                    (x: { tag: string; score: number }) => x.tag
                )
    );

    onMount(() => {
        function handleClickOutside(e: MouseEvent): void {
            if (
                containerEl &&
                !containerEl.contains(e.target as Node)
            ) {
                switchMode(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener(
                'mousedown',
                handleClickOutside
            );
    });

    async function switchMode(active: boolean): Promise<void> {
        editMode = active;

        if (active) {
            await tick();
            inputEl?.focus();
        } else {
            value = '';
        }
    }


    function confirm(): void {
        const trimmed: string = value.trim();

        if (!trimmed) return;

        onAdd?.(trimmed);

        value = '';
    }
    function autoFill(): void {
        selectSuggestion(autoFillVals[0]);
    }
    function selectSuggestion(tag: string): void {
        value = tag;
        confirm();
    }

    interface MatchPart { text: string; match: boolean }
    function highlightMatch(tag: string, query: string): MatchPart[] {
        const q: string = query.trim();
        if (!q) return [{ text: tag, match: false }];
        const idx: number = tag.toLowerCase().indexOf(q.toLowerCase());
        if (idx === -1) return [{ text: tag, match: false }];
        return [
            { text: tag.slice(0, idx), match: false },
            { text: tag.slice(idx, idx + q.length), match: true },
            { text: tag.slice(idx + q.length), match: false },
        ].filter((p: MatchPart) => p.text.length > 0);
    }
</script>

<div class="add-btn-wrapper">
    {#if editMode}
    <div class="edit-panel" bind:this={containerEl}>
        <input
                bind:this={inputEl}
                bind:value
                class="input"
                role="textbox"
                aria-label={__('assistants.builder.general.tag_input_aria')}
                placeholder={__('assistants.builder.general.tag_add')}
                disabled={disabled}
                onkeydown={(e: KeyboardEvent) => {
                    if(e.key === 'Tab') {
                        e.preventDefault();
                        autoFill();
                    }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirm();
                }
            }}
        />

        <button
                type="button"
                class="confirm-btn"
                class:ready={value.trim()}
                aria-label={__('assistants.builder.general.tag_confirm_aria')}
                disabled={!value.trim()}
                onclick={confirm}
        >
            <Tick02Icon size="1em" />
        </button>
    </div>
    {#if autoFillVals.length > 0}
        <div class="suggestions">
            {#each autoFillVals as tag (tag)}
                <button
                        type="button"
                        class="suggestion"
                        onclick={() => selectSuggestion(tag)}
                >
                    <span class="suggestion-label">
                        {#each highlightMatch(tag, value) as part}
                            <span class:match={part.match}>{part.text}</span>
                        {/each}
                    </span>
                </button>
            {/each}
        </div>
    {/if}
    {:else}
         <button class="add-button" onclick={() => switchMode(true)} disabled={disabled} >
             <span class="icon"><Add01Icon size="1em" /></span>
             <span class="label">{__('assistants.builder.general.tag_add')}</span>
         </button>
    {/if}
</div>


<style>
    .add-btn-wrapper{
        position: relative;
    }

    /* Collapsed: a dashed "add" pill echoing the tag chips, signalling an
       empty slot to fill. */
    .add-button{
        display: inline-flex;
        flex-direction: row;
        gap: var(--space-1_5);
        height: 2.25rem;
        width: max-content;
        align-items: center;
        padding: 0 var(--space-3) 0 var(--space-2_5);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
        background: transparent;
        border: 1.5px dashed var(--color-border);
        border-radius: var(--corner-full);
        cursor: pointer;
        transition:
            background-color var(--transition-fast),
            border-color var(--transition-fast),
            color var(--transition-fast);
    }
    .add-button:hover{
        color: var(--color-accent-text);
        border-color: var(--color-accent-300);
        background-color: var(--color-accent-100);
    }
    .add-button .icon{
        display: inline-flex;
        font-size: var(--font-size-sm);
        color: var(--color-text);
    }
    .add-button:hover .icon,
    .add-button:hover .label{
        color: var(--color-accent-text);
    }

    /* Edit: a solid pill that reads as a focused field, with a leading tag
       glyph and a trailing confirm affordance. */
    .edit-panel{
        position: relative;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: var(--space-1_5);
        height: 2.25rem;
        width: fit-content;
        min-width: 12rem;
        padding: 0 var(--space-1) 0 var(--space-2_5);
        background: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-full);
        animation: pill-in var(--transition-fast) ease-out;
    }
    @keyframes pill-in {
        from { opacity: 0; transform: scale(0.96); }
        to { opacity: 1; transform: scale(1); }
    }
    .confirm-btn{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 1.375rem;
        height: 1.375rem;
        padding: 0;
        border: none;
        border-radius: var(--corner-full);
        color: var(--color-text-muted);
        background: transparent;
        cursor: pointer;
        transition:
            background-color var(--transition-fast),
            color var(--transition-fast);
    }
    .confirm-btn.ready{
        color: var(--color-accent-text);
        background: var(--color-accent-100);
    }
    .confirm-btn.ready:hover{
        background: var(--color-accent-200);
    }
    .confirm-btn.ready:active{
        transform: scale(0.92);
    }
    .confirm-btn:disabled{
        opacity: 0.4;
        cursor: default;
    }
    input{
        display: inline-block;
        font-size: var(--font-size-xs);
        line-height: 2.25rem;
        color: var(--color-text);
        background: none;
        border: none;
        min-height: 0;
        height: 100%;
        width: 100%;
        padding: 0;
        resize: none;
    }
    input::placeholder{
        color: var(--color-text-muted);
    }
    .input:focus,
    .input:focus-visible{
        outline: none;
        box-shadow: none;
    }
    .confirm-btn:focus-visible{
        outline: none;
    }

    .suggestions{
        position: absolute;
        z-index: 1;
        top: calc(100% + var(--space-1_5));
        left: 0;
        min-width: 12rem;
        width: max-content;
        max-width: 18rem;
        height: fit-content;
        padding: var(--space-1);
        background: var(--color-surface-raised);
        border: var(--border);
        box-shadow: var(--elevation-2);
        border-radius: var(--corner-md);
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        animation: pill-in var(--transition-fast) ease-out;
    }
    .suggestion{
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-1_5) var(--space-2_5);
        text-align: left;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        border-radius: var(--corner-sm);
        cursor: pointer;
        transition:
            background-color var(--transition-fast),
            color var(--transition-fast);
    }
    .suggestion:hover{
        background: var(--color-hover);
        color: var(--color-text);
    }
    .suggestion-label .match{
        font-weight: var(--font-weight-medium);
        color: var(--color-accent-text);
    }
</style>
