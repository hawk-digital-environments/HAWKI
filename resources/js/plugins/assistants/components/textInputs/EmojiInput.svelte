<script lang="ts">
    import EmojiPicker from "$plugins/assistants/components/emojiPicker/EmojiPicker.svelte";
    import InputError from "../inputError/InputError.svelte";

    let {
        value = $bindable(""),
        maxlength,
        id,
        label = "",
        placeholder = "",
        hint,
        error,
        disabled = false,
        emojiAriaLabel,
    }: {
        value?: string;
        maxlength?: number;
        id?: string;
        label?: string;
        placeholder?: string;
        /** Helper text shown below the field. Replaces the auto character count when set. */
        hint?: string;
        error?: string;
        disabled?: boolean;
        emojiAriaLabel?: string;
    } = $props();

    function onInput(e: Event & { currentTarget: HTMLInputElement }) {
        const next = e.currentTarget.value;
        if (maxlength != null && next.length > maxlength) {
            // Revert the DOM value to keep it in sync with the clamped state.
            e.currentTarget.value = value;
            return;
        }
        value = next;
    }

    function onEmojiSelect(emoji: string) {
        const next = value + emoji;
        // Only append when it still fits, so we never split a multi-unit emoji.
        if (maxlength == null || next.length <= maxlength) {
            value = next;
        }
    }
</script>

<div class="emoji-input">
    {#if label || error}
        <div class="field-header">
            {#if label}
                <label for={id} class="label">{label}</label>
            {/if}
            <InputError message={error} />
        </div>
    {/if}

    <div class="input-wrapper" class:disabled>
        <input
            {id}
            type="text"
            {value}
            {placeholder}
            {disabled}
            {maxlength}
            oninput={onInput}
        />
        {#if !disabled}
            <EmojiPicker onSelect={onEmojiSelect} ariaLabel={emojiAriaLabel} />
        {/if}
    </div>

    {#if hint}
        <p class="hint">{hint}</p>
    {:else if maxlength != null}
        <p class="hint">
            <span>Emojis dürfen 2 Zeichen belegen.</span>
            <span>{value.length}/{maxlength}</span>
        </p>
    {/if}
</div>

<style>
    .emoji-input {
        display: flex;
        flex-direction: column;
    }

    .input-wrapper {
        display: flex;
        align-items: stretch;
        border: var(--border);
        border-radius: var(--corner-md);
        overflow: visible;
    }

    .input-wrapper:focus-within {
        /*border: var(--border-strong);*/
    }

    .input-wrapper.disabled {
        opacity: 0.5;
    }

    .input-wrapper input {
        flex: 1 1 auto;
        min-width: 0;
        border: none;
        border-radius: 0;
        background: transparent;
    }

    .hint {
        display: flex;
        justify-content: space-between;
        padding: 0 .5rem;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs) !important;
        margin: 0.2rem 0 0 0.2rem;
    }
</style>
