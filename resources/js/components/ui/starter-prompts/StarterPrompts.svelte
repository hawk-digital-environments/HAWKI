<!--
  @component Starter prompts for a chat's empty state, one button per prompt.
  What picking a prompt does (pre-fill the composer, send it) is up to the
  caller.

  Two layouts: `pills` (default), a centred, wrapping row for wide surfaces
  such as the chat page; and `list`, full-width rows split by hairlines for
  narrow panels, where wrapped pills would turn into ragged blobs.

  @example
  ```svelte
  <StarterPrompts prompts={section.starterPrompts} onselect={selectPrompt}
                  aria-label={__('chat.page.starterPrompts')}/>
  ```
-->
<script lang="ts">
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';

    interface Props {
        prompts: string[];
        onselect: (prompt: string) => void;
        'aria-label': string;
        disabled?: boolean;
        layout?: 'pills' | 'list';
    }

    const {prompts, onselect, 'aria-label': ariaLabel, disabled = false, layout = 'pills'}: Props = $props();
</script>

<ul class="starter-prompts {layout}" aria-label={ariaLabel}>
    {#each prompts as prompt (prompt)}
        <li>
            <button type="button" class="starter-prompt" {disabled} onclick={() => onselect(prompt)}>
                <span class="text">{prompt}</span>
                {#if layout === 'list'}
                    <span class="arrow" aria-hidden="true"><ArrowRight01Icon size="1em"/></span>
                {/if}
            </button>
        </li>
    {/each}
</ul>

<style>
    .starter-prompts {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .starter-prompt {
        color: var(--color-text);
        font-size: var(--font-size-sm);
        cursor: pointer;
    }

    .starter-prompt:focus-visible { outline: var(--focus-outline); outline-offset: 2px; }
    .starter-prompt:disabled { cursor: not-allowed; color: var(--color-text-muted); }

    /* ── pills ── */
    .pills {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        justify-content: center;
    }

    .pills .starter-prompt {
        padding: var(--space-2) var(--space-4);
        border: var(--divider);
        border-radius: var(--corner-full);
        background: var(--color-surface-raised);
        transition: border-color 120ms ease, background-color 120ms ease;
    }

    .pills .starter-prompt:hover:not(:disabled) { border-color: var(--color-interactive); }

    /* ── list ── */
    .list {
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    /* Light hairlines inset to the text, so the rows read as one quiet group. */
    .list li + li {
        position: relative;
    }

    .list li + li::before {
        content: '';
        position: absolute;
        top: 0;
        right: var(--space-3);
        left: var(--space-3);
        border-top: var(--divider-width) solid color-mix(in oklab, var(--color-border) 55%, transparent);
        pointer-events: none;
    }

    /* The hover fill replaces the lines around the hovered row. */
    .list li:hover::before,
    .list li:hover + li::before {
        opacity: 0;
    }

    .list .starter-prompt {
        display: flex;
        width: 100%;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2_5) var(--space-3);
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        line-height: var(--line-height-normal);
        text-align: left;
    }

    .list .text {
        flex: 1;
        min-width: 0;
    }

    .list .arrow {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .list .starter-prompt:hover:not(:disabled) {
        background: var(--color-surface-light);
    }
</style>
