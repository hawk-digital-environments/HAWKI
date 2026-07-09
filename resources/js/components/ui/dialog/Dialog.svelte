<!--
  @component Flexible modal dialog primitive. Wraps bits-ui Dialog with a
  structured header / body / footer layout and an optional close button.
  Use ConfirmDialog or InfoDialog for pre-built variants; use this directly
  for dialogs that need custom body content or a non-standard layout.
-->
<script lang="ts">

    import type {Snippet} from 'svelte';
    import {Dialog as DialogPrimitive, type DialogContentProps, type DialogDescriptionProps, type DialogOverlayProps, type DialogTitleProps, mergeProps} from 'bits-ui';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {__} from '$lib/utils/translator.js';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';

    interface Props {
        /** Whether the dialog is open. Supports bind:open for two-way binding. */
        open?: boolean;
        /** Called when the dialog requests an open-state change. */
        onOpenChange?: (open: boolean) => void;
        /** When true, the dialog shows a "close button" in the top-right corner. */
        closable?: boolean;
        /** An optional trigger element for the dialog. Can be a string or a snippet that receives props to spread on the trigger element. */
        trigger?: Snippet<[{ props: Record<string, any> }]> | string;
        /** The title of the dialog. Can be a string or a Svelte snippet. */
        title?: Snippet | string;
        /** Additional props to apply to the DialogTitle component. */
        titleProps?: DialogTitleProps;
        /** An optional description to display below the title. Can be either a string or a snippet. */
        description?: Snippet | string;
        /** Additional props to apply to the DialogDescription component. */
        descriptionProps?: Omit<DialogDescriptionProps, 'children'>;
        /** Additional props to apply to the header container. */
        headerProps?: Omit<HTMLAttributes<HTMLDivElement>, 'children'>;
        /** An optional footer to display at the bottom of the dialog. Can be either a string or a snippet. */
        footer?: Snippet | string;
        /** Additional props to apply to the footer container. */
        footerProps?: Omit<HTMLAttributes<HTMLDivElement>, 'children'>;
        /** The main content of the dialog, rendered between the header and footer. This is a Svelte snippet that receives no arguments. */
        children?: Snippet;
        /** Additional props to apply to the DialogContent component. */
        contentProps?: Omit<DialogContentProps, 'children'>;
        /** Additional props to apply to the DialogOverlay component. */
        overlayProps?: Omit<DialogOverlayProps, 'children'>;
    }

    const {
        open = $bindable(false),
        closable = true,
        onOpenChange,
        trigger,
        title,
        titleProps,
        description,
        descriptionProps,
        headerProps,
        footer,
        footerProps,
        children,
        contentProps,
        overlayProps

    }: Props = $props();
</script>

<DialogPrimitive.Root {open} onOpenChange={onOpenChange}>
    {#if trigger}
        <DialogPrimitive.Trigger>
            {#snippet child({props})}
                {#if typeof trigger === 'string'}
                    <button {...props} type="button">{trigger}</button>
                {:else}
                    {trigger?.({props})}
                {/if}
            {/snippet}
        </DialogPrimitive.Trigger>
    {/if}
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay {...mergeProps({class: 'dialog-overlay'}, overlayProps)}/>
        <DialogPrimitive.Content {...mergeProps({class: 'dialog-content'}, contentProps)}>
            {#if title || description}
                <div {...mergeProps({class: 'dialog-header'}, headerProps)}>
                    {#if title}
                        <DialogPrimitive.Title {...mergeProps({class: 'dialog-title'}, titleProps)}>
                            <SnippetOrString value={title}/>
                        </DialogPrimitive.Title>
                    {/if}

                    {#if description}
                        <DialogPrimitive.Description {...mergeProps({class: 'dialog-description'}, descriptionProps)}>
                            <SnippetOrString value={description}/>
                        </DialogPrimitive.Description>
                    {/if}
                </div>
            {/if}

            {@render children?.()}

            {#if footer}
                <div {...mergeProps({class: 'dialog-footer'}, footerProps)}>
                    <SnippetOrString value={footer}/>
                </div>
            {/if}
            {#if closable}
                <DialogPrimitive.Close class="dialog-close" aria-label={__('ui.dialog.closeLabel')}>
                    <Cancel01Icon size={16}/>
                </DialogPrimitive.Close>
            {/if}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    :global(.dialog-header) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    :global(.dialog-title) {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
    }

    :global(.dialog-description) {
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    :global(.dialog-content) {
        --dialog-bg: var(--color-surface-raised);
        --dialog-border: var(--color-border);

        position: fixed;
        top: 50%;
        left: 50%;
        z-index: 50;
        display: grid;
        width: 100%;
        max-width: 32rem;
        translate: -50% -50%;
        gap: var(--space-4);
        border: var(--border);
        border-color: var(--dialog-border);
        border-radius: var(--corner-md);
        background-color: var(--dialog-bg);
        padding: var(--space-6);
        box-shadow: var(--elevation-2);

        &[data-state="open"] {
            animation: dialog-content-in var(--duration-normal, 200ms) var(--easing-default, ease);
        }

        &[data-state="closed"] {
            animation: dialog-content-out var(--duration-normal, 200ms) var(--easing-default, ease);
        }
    }

    :global(.dialog-close) {
        position: absolute;
        top: var(--space-4);
        right: var(--space-4);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--corner-sm);
        opacity: 0.7;
        transition: opacity var(--duration-fast, 150ms);
        color: var(--color-text-muted);
        background: none;
        border: none;
        cursor: pointer;
        padding: var(--space-1);

        &:hover {
            opacity: 1;
        }

        &:focus-visible {
            outline: 2px solid var(--color-focus-ring);
            outline-offset: 2px;
        }

        &:disabled {
            pointer-events: none;
        }
    }

    @keyframes dialog-content-in {
        from {
            opacity: 0;
            scale: 0.95;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes dialog-content-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.95;
        }
    }

    :global(.dialog-overlay) {
        position: fixed;
        inset: 0;
        z-index: 50;
        background-color: color-mix(in oklch, var(--color-bg) 80%, transparent);

        &[data-state="open"] {
            animation: dialog-fade-in var(--duration-normal, 200ms) var(--easing-default, ease);
        }

        &[data-state="closed"] {
            animation: dialog-fade-out var(--duration-normal, 200ms) var(--easing-default, ease);
        }
    }

    :global(.dialog-footer) {
        display: flex;
        flex-direction: row;
        justify-content: flex-end;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    @keyframes dialog-fade-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes dialog-fade-out {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }
</style>
