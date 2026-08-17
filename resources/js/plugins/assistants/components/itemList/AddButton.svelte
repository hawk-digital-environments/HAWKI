<script lang="ts">

    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import Add01Icon from '$lib/components/ui/icons/iconset/Add01Icon.svelte';
    import { tick, onMount } from 'svelte';

    let {
        name,
        disabled = false,
        onAdd,
    } = $props<{
        name?: string;
        disabled?: boolean;
        onAdd?: (value: string) => void,
    }>();

    let editMode = $state(false);
    let inputEl = $state<HTMLElement | null>(null);
    let containerEl = $state<HTMLElement | null>(null);

    onMount(() => {
        function handleClickOutside(e: MouseEvent) {
            if (containerEl && !containerEl.contains(e.target as Node)) {
                switchMode(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    async function switchMode(active: boolean) {
        editMode = active;
        if (active) {
            await tick();
            inputEl?.focus();
        } else if (inputEl) {
            inputEl.innerText = '';
        }
    }

    function confirm() {
        const value = inputEl?.innerText?.trim();
        if (!value) return;
        onAdd?.(value);
        if(inputEl)
            inputEl.innerText = '';
    }

</script>

{#if editMode}
    <div class="edit-panel" bind:this={containerEl}>
        <span
            role="textbox"
            aria-label="AssistantTag eingeben"
            aria-multiline="false"
            tabindex="0"
            class="input"
            bind:this={inputEl}
            contenteditable="plaintext-only"
            onkeydown={(e) => {
                if(e.key === 'Enter'){
                    e.preventDefault();
                    confirm();
                }
            }}
        ></span>
        <button type="button" class="confirm-btn" aria-label="Bestätigen" onclick={confirm}><Tick02Icon size="1em" /></button>
    </div>
{:else}
    <button class="add-button" onclick={() => switchMode(true)} disabled={disabled} >
        <span class="icon"><Add01Icon size="1em" /></span>
        <span class="label">{name} hinzufügen</span>
    </button>
{/if}

<style>
    .add-button,
    .edit-panel {
        display: flex;
        flex-direction: row;
        gap: .5rem;
        height: 2.5rem;
        width: max-content;
        min-width: 0;
        max-width: 100%;
        align-items: center;
        justify-content: space-between;
        padding: 0 .5rem;
        font-size: var(--font-size-xs);
        border-radius: var(--corner-md);
        border: var(--border);
        transition: all var(--transition-fast);
        margin-bottom: .5rem;
        background: var(--color-surface-raised);

    }
    .add-button:hover{
        background-color: var(--color-hover);
        color: var(--color-text);
    }
    .edit-panel {
        min-width: 100%;
    }

    .icon{
        display: inline-flex;
        align-items: center;
        font-size: var(--font-size-sm);
    }
    .label{
        display: inline-flex;
        align-items: center;
    }
    .confirm-btn{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 1.75rem;
        height: 1.75rem;
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
    .confirm-btn:hover{
        color: var(--color-accent-text);
        background: var(--color-accent-100);
    }
    .confirm-btn :global(svg){
        display: block;
    }

    .input{
        width: 100%;
        min-width: 3rem;
        height: 1.2rem;
        resize: none;
    }
    .input:focus{
        outline: none;
    }
</style>
