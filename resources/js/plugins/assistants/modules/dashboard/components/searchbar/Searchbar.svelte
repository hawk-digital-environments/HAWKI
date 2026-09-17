<script lang="ts">

    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import Loading03Icon from '$lib/components/ui/icons/iconset/Loading03Icon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    const {__} = useTranslator();
    let {
        defaultValue = "",
        loading = false,
        loadingLabel = "",
        onChange,
    } = $props<{
        defaultValue?: string;
        loading?: boolean;
        loadingLabel?: string;
        onChange: (query: string) => void;
    }>();

    const initialQuery = defaultValue;
    let text = $state(initialQuery);
    let emittedChange = initialQuery;

    let timer: ReturnType<typeof setTimeout>;

    function emit(query: string) {
        emittedChange = query;
        onChange(query);
    }

    function handleInput() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (text !== emittedChange) emit(text);
        }, 300);
    }

    /** Manual trigger (Enter key or icon click): cancels a pending debounce and
     *  emits unconditionally, so an unchanged query still re-searches. */
    function submit() {
        clearTimeout(timer);
        emit(text);
    }
</script>

<div class="searchbar input">
    <div class="icon-wrapper">
        {#if loading}
            <Tooltip tooltip={loadingLabel} delayDuration={300} focusable={false}>
                {#snippet children({props})}
                    <span
                            class="icon icon--loading"
                            role="status"
                            aria-label={loadingLabel}
                            {...props}
                    >
                        <Loading03Icon size="1em" />
                    </span>
                {/snippet}
            </Tooltip>
        {:else}
            <button
                    type="button"
                    class="icon icon--submit"
                    onclick={submit}
                    aria-label={__('assistants.browser.search')}
            >
                <Search01Icon size="1em" />
            </button>
        {/if}
    </div>
    <label for="searchbar">{__('assistants.browser.search')}</label>
    <input
            id="searchbar"
            type="text"
            bind:value={text}
            oninput={handleInput}
            onkeydown={(event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submit();
                }
            }}
    />
</div>

<style>
    .searchbar{
        display: flex;
        flex-direction: row;
        width: 100%;
        gap: var(--space-1);
        padding: var(--space-2_5) var(--space-3);
        align-items: center;
        border: var(--border);
        border-radius: var(--corner-md);
        background-color: var(--color-surface-raised);
        transition:
            border-color var(--duration-fast),
            box-shadow var(--duration-fast);
    }
    .searchbar:focus-within{
        border-color: var(--color-accent-300);
        box-shadow: 0 0 0 3px var(--color-accent-100);
    }
    .icon-wrapper{
        display: flex;
        justify-content: center;
        align-items: center;
        color: var(--color-text-muted);
        cursor: default;
        user-select: none;
    }
    .icon{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 0;
        font-size: var(--font-size-lg);
    }
    .icon--submit{
        padding: 0;
        border: none;
        background: none;
        color: inherit;
        cursor: pointer;
    }
    .icon--submit:focus-visible{
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
        border-radius: var(--corner-sm);
    }
    .icon--loading{
        animation: searchbar-spin 700ms linear infinite;
    }

    @keyframes searchbar-spin{
        to{
            rotate: 1turn;
        }
    }

    @media (prefers-reduced-motion: reduce){
        .icon--loading{
            animation-duration: 1400ms;
        }
    }
    label{
        display: none;
    }
    input{
        width: 100%;
        min-height: 1.5rem !important;
        color: var(--color-text);
        font-size: var(--font-size-sm);
        background-color: transparent;
        border: none;
        outline: none;
    }
    input::placeholder{
        color: var(--color-text-muted);
    }
</style>
