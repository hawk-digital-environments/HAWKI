<!--
  @component Flexible modal dialog primitive. Wraps bits-ui Dialog with a
  structured header / body / footer layout and an optional close button.
  Use ConfirmDialog or InfoDialog for pre-built variants; use this directly
  for dialogs that need custom body content or a non-standard layout.

  The content is a flex column: pinned header and footer (flex: none) around
  a `.dialog-body` region that grows with the content and becomes the scroll
  container once the dialog hits its max-height (`flex: 1 1 auto` with
  `min-height: 0` and `overflow-y: auto`). Consumers that need to style the
  scroll region itself (scrollbar gutter, hidden scrollbars) can pass
  `bodyProps`; consumers that manage their own internal scrollers keep the
  content shrinkable (`min-height: 0`) so the body never double-scrolls.

  The dialog is fully controlled: bits-ui never flips the open state on its
  own. Every close request (Escape, outside click, the X button) is reported
  via `onOpenChange(false)` and the dialog only closes once the parent sets
  `open` to false. A parent can therefore veto a close (e.g. to ask about
  unsaved changes first) simply by leaving `open` untouched.

  Usage — custom body content with a title, description and footer:
    <Dialog {open} onOpenChange={(o) => open = o} title="Edit prompt" description="Changes apply immediately.">
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

  Initial focus: the close button is the first element in DOM order, so when
  the dialog opens, keyboard/screen-reader users land on it first and can
  leave immediately (bits-ui focuses the first tabbable element). Dialogs
  that deliberately want to focus an input instead should NOT rely on the
  `autofocus` attribute (bits-ui's auto focus runs afterwards and would win);
  pass `contentProps.onOpenAutoFocus`, call `event.preventDefault()` and focus
  the input yourself.

  Use `role="alertdialog"` for blocking confirmations (see ConfirmDialog); the
  default is a regular `dialog`.
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
        /** Whether the dialog is open. Controlled: update it from `onOpenChange`. */
        open?: boolean;
        /** Called when the dialog requests an open-state change; the parent decides by updating `open`. */
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
        /** Additional props to apply to the body region that wraps the main content. */
        bodyProps?: Omit<HTMLAttributes<HTMLDivElement>, 'children'>;
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
        /**
         * ARIA role of the dialog surface. Use `alertdialog` for modal
         * confirmations that interrupt the user and require a response.
         * @default 'dialog'
         */
        role?: 'dialog' | 'alertdialog';
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
        children,
        contentProps,
        overlayProps,
        role = 'dialog'
    }: Props = $props();
</script>

<!-- Function binding: bits-ui reads `open` from our prop and routes every
     change request through `onOpenChange` instead of mutating its own copy.
     Without this, an outside click or Escape would close the dialog locally
     while the parent's `open` still says true, leaving both out of sync. -->
<DialogPrimitive.Root bind:open={() => open, (value) => onOpenChange?.(value)}>
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
            <!-- bits-ui always emits role="dialog" on its content props, so the
                 element is rendered here to let `role` override it. -->
            {#snippet child({props})}
                <div {...props} {role}>
                    {#if closable}
                        <DialogPrimitive.Close class="dialog-close" aria-label={__('ui.dialog.closeLabel')}>
                            <Cancel01Icon size={16}/>
                        </DialogPrimitive.Close>
                    {/if}

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

                    <div {...mergeProps({class: 'dialog-body'}, bodyProps)}>
                        {@render children?.()}
                    </div>

                    {#if footer}
                        <div {...mergeProps({class: 'dialog-footer'}, footerProps)}>
                            <SnippetOrString value={footer}/>
                        </div>
                    {/if}
                </div>
            {/snippet}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    :global(.dialog-header) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        flex: none;
    }

    :global(.dialog-body) {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
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
        display: flex;
        flex-direction: column;
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

    /* Doubled class to out-specify ActionIcon's scoped `button.svelte-… { position: relative }`
       (both live in the `components` cascade layer, so specificity decides). */
    :global(.dialog-close.dialog-close) {
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
        flex: none;
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
