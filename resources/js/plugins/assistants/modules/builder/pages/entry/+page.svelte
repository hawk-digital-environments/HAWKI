<!--
  @component Creation-method landing. Lets the user pick how to build a new
  assistant. Advanced is wired up; Assisted is shown as an upcoming option.
-->
<script lang="ts">
    import {goto} from "$app/navigation";
    import Configuration01Icon from "$lib/components/generic/icons/iconset/Configuration01Icon.svelte";
    import SparklesIcon from "$lib/components/generic/icons/iconset/SparklesIcon.svelte";
    import { __ } from "$lib/utils/translator"
</script>

<div class="entry">
    <header class="entry-header">
        <h2 class="entry-title">{__('assistants.entry.title')}</h2>
        <p class="entry-description">{__('assistants.entry.description')}</p>
    </header>

    <div class="entry-options">
        <button
            type="button"
            class="option"
            onclick={() => goto('/builder/advanced/general')}
        >
            <span class="option-icon"><Configuration01Icon size={28} /></span>
            <span class="option-title">{__('assistants.entry.advanced')}</span>
            <span class="option-text">{__('assistants.entry.advanced_description')}</span>
        </button>

        <button type="button" class="option" disabled aria-disabled="true">
            <span class="badge">{__('assistants.entry.coming_soon')}</span>
            <span class="option-icon"><SparklesIcon size={28} /></span>
            <span class="option-title">{__('assistants.entry.assisted')}</span>
            <span class="option-text">{__('assistants.entry.assisted_description')}</span>
        </button>
    </div>
</div>

<style>
    .entry {
        max-width: 48rem;
        margin: 0 auto;
        padding: var(--space-6);
    }

    .entry-header {
        margin-bottom: var(--space-5);
    }

    .entry-title {
        margin: 0 0 var(--space-1);
        font-size: var(--font-size-lg, 1.375rem);
    }

    .entry-description {
        margin: 0;
        color: var(--color-text-muted);
    }

    .entry-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
        gap: var(--space-4);
    }

    .option {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-2);
        padding: var(--space-4);
        text-align: left;
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        color: var(--color-text);
        cursor: pointer;
        transition:
            border-color var(--duration-fast),
            background var(--duration-fast);
    }

    .option:hover:not(:disabled) {
        border-color: var(--color-accent-200);
        background: var(--color-hover);
    }

    .option:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .option-icon {
        display: inline-flex;
        color: var(--color-accent-text);
    }

    .option-title {
        font-weight: 600;
    }

    .option-text {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm, 0.875rem);
    }

    .badge {
        position: absolute;
        top: var(--space-3);
        right: var(--space-3);
        padding: var(--space-1) var(--space-2);
        border-radius: var(--corner-full);
        background: var(--color-accent-100);
        color: var(--color-accent-text);
        font-size: var(--font-size-xs, 0.75rem);
    }
</style>
