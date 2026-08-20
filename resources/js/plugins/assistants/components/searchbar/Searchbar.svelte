<script lang="ts">

    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    let {
        value = $bindable(""),
    } = $props<{
        value?: string;
    }>();

    let timer: ReturnType<typeof setTimeout>;
    let inputEl: HTMLInputElement;

    function handleInput() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            value = inputEl.value;
        }, 300);
    }
</script>

<div class="searchbar input">
    <div class="icon-wrapper">
        <span class="icon"><Search01Icon size="1em" /></span>
    </div>
    <label for="searchbar">Search Input</label>
    <input
            id="searchbar"
            type="text"
            bind:this={inputEl}
            oninput={handleInput}
            value={value}
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
