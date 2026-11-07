<!--
  @component Modal showing an assistant's detail view over the list it was
  opened from, filling the main content area beside the sidebar. The list
  stays mounted beneath it, so its scroll position, filters and loaded page
  survive the round trip untouched. The sidebar stays usable: no focus trap,
  no scroll lock, and clicks on it don't count as a close request (navigating
  away unmounts the list, and the modal with it).

  Rendered only while open (mount = open): the store→detail morph
  (morphToDetail) captures the moment it mounts or unmounts, so it carries no
  enter/exit animation of its own. Escape and the detail's back button both
  ask the owner to close via `onClose`.
-->
<script lang="ts">
    import {Dialog as DialogPrimitive} from 'bits-ui';
    import AssistantDetail from '$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetail.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';

    interface Props {
        /** Id of the assistant to show. */
        id: string;
        /** Close request (Escape, back button). */
        onClose: () => void;
        /** After a successful delete. Defaults to `onClose`. */
        onDeleted?: () => void;
        /** The list's copy of the assistant, shown while the detail loads. */
        initial?: Assistant;
    }

    const {id, onClose, onDeleted, initial}: Props = $props();
    const {__} = useTranslator();
</script>

<!-- Controlled: bits-ui reports every close request instead of closing itself. -->
<DialogPrimitive.Root bind:open={() => true, (value) => { if (!value) onClose(); }}>
    <!-- Into the main area (positioned, clipped), so the sidebar stays beside it. -->
    <DialogPrimitive.Portal to="#main-content">
        <DialogPrimitive.Content trapFocus={false} preventScroll={false} interactOutsideBehavior="ignore">
            {#snippet child({props})}
                <div {...props} class="detail-modal">
                    <DialogPrimitive.Title class="u-sr-only">{__('assistants.detail.dialog_title')}</DialogPrimitive.Title>
                    <AssistantDetail {id} onBack={onClose} {onDeleted} {initial} />
                </div>
            {/snippet}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    .detail-modal {
        position: absolute;
        inset: 0;
        /* Above the list's own layers (e.g. its header bar), below the mobile
           navigation trigger (z-index 10), which keeps opening the sidebar. */
        z-index: 2;
        background: var(--color-surface-raised);
        /* Morph layer for the modal surface: fades in over the receding list
           on open and out on close (morphToDetail.css). */
        view-transition-name: assistant-modal;
    }
</style>
