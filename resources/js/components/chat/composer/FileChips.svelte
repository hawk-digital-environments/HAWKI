<!--
  @component Row of removable file chips. Files incompatible with the current
  model are shown with a warning icon and a rose-tinted background.
  Renders nothing when the files array is empty.
-->
<script lang="ts">
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import FilePreview from '$lib/components/chat/composer/utils/FilePreview.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {mergeProps} from 'bits-ui';
    import {cubicIn} from 'svelte/easing';
    import RadialProgress from '$lib/components/ui/radial-progress/RadialProgress.svelte';
    import {__} from '$lib/utils/translator.js';
    import Alert02Icon from '$lib/components/ui/icons/iconset/Alert02Icon.svelte';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {tick} from 'svelte';

    const composerContext = useComposerContext();

    let chipsEl = $state(null as HTMLDivElement | null);

    // Roving tabindex, matching the conflict picker: the chip row is a single tab
    // stop, arrow keys move between the chips inside it.
    let activeIndex = $state(0);

    // Keep the roving index in range when chips are added or removed.
    $effect(() => {
        if (activeIndex > composerContext.attachments.list.length - 1) {
            activeIndex = 0;
        }
    });

    function chipButtons(): HTMLElement[] {
        return chipsEl ? Array.from(chipsEl.querySelectorAll<HTMLElement>('[data-file-chip]')) : [];
    }

    function focusChip(index: number) {
        const chip = chipButtons()[index];
        if (!chip) return;
        activeIndex = index;
        chip.focus();
    }

    /**
     * Removes a chip and lands focus somewhere sensible: the chip that slid into the
     * removed slot, otherwise the preceding one, otherwise the textarea once the row
     * is empty. Without this, focus falls back to `<body>` and keyboard users have to
     * tab in from the top of the page again.
     */
    async function removeAndMoveFocus(file: File, index: number) {
        composerContext.attachments.remove(file);
        // tick() flushes the re-render; the extra frame lets the removed chip's
        // (zero-duration) out-transition finish so it is out of the DOM before we
        // pick the chip to focus by index.
        await tick();
        await new Promise(requestAnimationFrame);

        if (!composerContext.attachments.hasAny) {
            composerContext.focusInput();
            return;
        }

        const chips = chipButtons();
        if (chips.length === 0) {
            composerContext.focusInput();
            return;
        }
        focusChip(Math.min(index, chips.length - 1));
    }

    // Enter/Space is handled here rather than through the button's click handler so
    // keyboard removal can move focus onward while a mouse click returns to the textarea.
    // preventDefault stops the browser from firing a synthetic click afterwards.
    function onChipKeydown(event: KeyboardEvent, file: File, index: number) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            void removeAndMoveFocus(file, index);
            return;
        }

        const last = composerContext.attachments.list.length - 1;
        let target: number | null = null;

        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                target = Math.min(index + 1, last);
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                target = Math.max(index - 1, 0);
                break;
            case 'Home':
                target = 0;
                break;
            case 'End':
                target = last;
                break;
        }

        if (target === null) return;
        event.preventDefault();
        focusChip(target);
    }

    function onChipClick(file: File) {
        composerContext.attachments.remove(file);
        composerContext.focusInput();
    }

    // The issue is either that we can not upload files at all,
    // or if we want to upload an image, that the model does not support vision.
    // Currently, both cases lead to a generic error, we can make this more specific in the future if needed.
    const currentModelHasFileIssue = $derived.by(() => {
        return composerContext.modelUsage.issues.some(issue => issue.type === 'no_file_upload' || issue.type === 'no_vision');
    });

    // Reverse of the chip add transition: slide the mask down + fade out.
    // Only the last chip animates out — earlier removals reflow instantly.
    function maskSlideDown(_node: HTMLElement) {
        if (composerContext.attachments.hasAny) return {duration: 0};
        return {
            duration: 500,
            easing: cubicIn,
            css: (t: number) => `opacity: ${t}; transform: translateY(${(1 - t) * 100}%);`
        };
    }
</script>

{#if composerContext.attachments.hasAny}
    <div
        class="file-chips"
        class:file-chips--no-conflict={!currentModelHasFileIssue}
        role="group"
        aria-label={__('chat.composer.fileChips.listAriaLabel')}
        bind:this={chipsEl}
    >
        {#each composerContext.attachments.list as file, i (`${file.name}-${i}`)}
            {@const conflict = currentModelHasFileIssue}
            <span class="file-chip-mask" out:maskSlideDown|global style:--file-chip-delay={`${Math.min(i, 4) * 35}ms`}>
                <Tooltip tooltip={file.name}>
                    {#snippet children(a)}
                        {@const sendIssue = composerContext.sendStatus?.getFileIssue(file)}
                        {@const progress = composerContext.sendStatus?.getFileProgress(file) }
                        {@const conflictMessage = conflict ? __('chat.composer.fileChips.conflictMessage') : null}
                        {@const issueMessage = sendIssue ? __('chat.composer.fileChips.uploadError', {issue: sendIssue}) : conflictMessage}
                        {@const hasIssue = conflict || !!sendIssue}
                        <button
                            {...mergeProps(
                                a.props,
                                {
                                    class: [
                                        'file-chip',
                                        hasIssue ? 'file-chip--conflict' : 'file-chip--default'
                                    ],
                                    title: issueMessage,
                                    onclick: () => onChipClick(file),
                                    onkeydown: (event: KeyboardEvent) => onChipKeydown(event, file, i),
                                    onfocus: () => activeIndex = i,
                                    tabindex: i === activeIndex ? 0 : -1,
                                    'data-file-chip': '',
                                    'aria-label': __('chat.composer.fileChips.removeFileAriaLabel', {file: file.name})
                                }
                            )}
                        >
                            <FilePreview file={file}/>
                            {#if conflict}
                                <Alert02Icon size={12} class="file-chip-warning"/>
                            {/if}
                            {#if progress !== null}
                                <RadialProgress value={progress}/>
                            {/if}
                            <span class="file-chip-name">{file.name}</span>
                            <Cancel01Icon size={12} class="file-chip-remove"/>
                        </button>
                    {/snippet}
                </Tooltip>
            </span>
        {/each}
    </div>
{/if}

<style>
    .file-chips {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
    }

    .file-chips--no-conflict {
        padding-top: var(--space-2);
    }

    .file-chip-mask {
        display: inline-flex;
        max-width: calc(0.25rem * 44);
        overflow: hidden;
        border-radius: var(--corner-sm);
    }

    .file-chip {
        display: inline-flex;
        max-width: 100%;
        align-items: center;
        gap: var(--space-1);
        border-radius: var(--corner-sm);
        border: none;
        padding-block: var(--space-1);
        padding-inline-start: var(--space-1);
        padding-inline-end: var(--space-2);
        font-size: var(--font-size-xxs);
        animation: file-chip-slide-up var(--duration-medium, 500ms) var(--easing-spring) both;
        animation-delay: var(--file-chip-delay, 0ms);
    }

    /* The chip's mask clips overflow for the slide animation, so the focus ring is
       drawn inside the chip rather than around it. */
    .file-chip:focus-visible {
        outline: 1px solid var(--color-focus-ring);
        outline-offset: -2px;
        border-radius: var(--corner-sm);
    }

    .file-chip--default:focus-visible {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .file-chip--conflict:focus-visible {
        background-color: color-mix(in oklch, var(--color-error) 18%, transparent);
    }

    .file-chip--default {
        background-color: var(--color-surface);
        color: var(--color-text-muted);
        cursor: pointer;
        border: none;
    }

    .file-chip--default:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .file-chip--with-preview {
        padding-left: var(--space-1, calc(0.25rem * 1));
    }

    .file-chip--conflict {
        background-color: color-mix(in oklch, var(--color-error) 12%, transparent);
        color: var(--color-error);
        cursor: pointer;
    }

    .file-chip--conflict:hover {
        background-color: color-mix(in oklch, var(--color-error) 18%, transparent);
    }

    :global(.file-chip-warning) {
        flex-shrink: 0;
    }

    .file-chip-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        pointer-events: none;
    }

    :global(.file-chip-remove) {
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .file-chip--conflict :global(.file-chip-remove) {
        color: color-mix(in oklch, var(--color-error) 70%, transparent);
    }

    .file-chip--default:hover :global(.file-chip-remove),
    .file-chip--default:focus-visible :global(.file-chip-remove) {
        color: var(--color-text);
    }

    .file-chip--conflict:hover :global(.file-chip-remove),
    .file-chip--conflict:focus-visible :global(.file-chip-remove) {
        color: var(--color-error);
    }

    @keyframes file-chip-slide-up {
        from {
            opacity: 0;
            transform: translateY(100%);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
