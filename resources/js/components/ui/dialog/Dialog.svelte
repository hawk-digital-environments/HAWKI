<!--
  @component Flexible modal dialog primitive. Wraps bits-ui Dialog with a
  structured header / body / footer layout and an optional close button.
  Use ConfirmDialog or InfoDialog for pre-built variants; use this directly
  for dialogs that need custom body content or a non-standard layout.

  Usage — custom body content with a title, description and footer:
    <Dialog bind:open title="Edit prompt" description="Changes apply immediately.">
        {#snippet children()}
            <Textarea bind:value={draft}/>
        {/snippet}
        {#snippet footer()}
            <Button variant="ghost" onclick={() => open = false}>Cancel</Button>
            <Button variant="fill" onclick={handleSave}>Save</Button>
        {/snippet}
    </Dialog>

  Usage — with an inline trigger (snippet receives `props` that MUST be
  spread onto the trigger's root element):
    <Dialog>
        {#snippet trigger({props})}
            <button {...props}>Open settings</button>
        {/snippet}
        {#snippet title()}Settings{/snippet}
        {#snippet children()}...{/snippet}
    </Dialog>
-->
<script lang="ts">

    import type {Snippet} from 'svelte';
    import {Dialog as DialogPrimitive, type DialogContentProps, type DialogDescriptionProps, type DialogOverlayProps, type DialogTitleProps, mergeProps} from 'bits-ui';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();
    
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
        /** When provided, wraps the main content in a `.dialog-body` region that
            grows with the content and becomes the scroll container once the dialog
            hits its max-height; consumers managing their own scrollers style it
            through these props (e.g. to opt out of clipping). */
        bodyProps?: Omit<HTMLAttributes<HTMLDivElement>, 'children'>;
        /** An optional footer to display at the bottom of the dialog. Can be either a string or a snippet. */
        footer?: Snippet | string;
        /** Rendered at the end of the header row (e.g. an avatar next to the title). */
        headerTrailing?: Snippet;
        /** Additional props to apply to the footer container. */
        footerProps?: Omit<HTMLAttributes<HTMLDivElement>, 'children'>;
        /** The main content of the dialog, rendered between the header and footer. This is a Svelte snippet that receives no arguments. */
        children?: Snippet;
        /** Additional props to apply to the DialogContent component. */
        contentProps?: Omit<DialogContentProps, 'children'>;
        /** ARIA role of the dialog surface; `alertdialog` for blocking confirmations. */
        role?: 'dialog' | 'alertdialog';
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
        bodyProps,
        footer,
        footerProps,
        headerTrailing,
        children,
        contentProps,
        overlayProps,
        role

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
        <DialogPrimitive.Content {...mergeProps({class: 'dialog-content', ...(role ? {role} : {})}, contentProps)}>
            {#if title || description || headerTrailing}
                <div {...mergeProps({class: headerTrailing ? 'dialog-header dialog-header--row' : 'dialog-header'}, headerProps)}>
                    <div class="dialog-header-text">
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
                    {#if headerTrailing}
                        {@render headerTrailing()}
                    {/if}
                </div>
            {/if}

            {#if bodyProps}
                <div {...mergeProps({class: 'dialog-body'}, bodyProps)}>
                    {@render children?.()}
                </div>
            {:else}
                {@render children?.()}
            {/if}

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

    :global(.dialog-header.dialog-header--row) {
        flex-direction: row;
        align-items: center;
        gap: var(--space-4);
        padding-inline-end: calc(var(--space-4) + var(--space-6) + var(--space-2));
    }

    :global(.dialog-header-text) {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
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
        z-index: var(--layer-overlay);
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

    :global(.dialog-body) {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        overflow-y: auto;
        scrollbar-gutter: stable;
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
        z-index: var(--layer-overlay);
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
