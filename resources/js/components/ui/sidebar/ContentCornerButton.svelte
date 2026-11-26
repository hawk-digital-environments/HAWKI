<!--
  @component Icon button floating in a top corner of the main content area,
  above the page and its top fade: the nav toggle at the start corner, page
  actions such as "new chat" at the end corner. `label` names it for screen
  readers and in its tooltip.

  @example
  ```svelte
  <ContentCornerButton corner="end" icon={BubbleChatAddIcon} label="New chat" onclick={newChat}/>
  ```
-->
<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends HTMLButtonAttributes {
        icon: IconComponent;
        /** Accessible name and tooltip. */
        label: string;
        /** Which top corner of the content area it floats in. */
        corner?: 'start' | 'end';
    }

    const {icon: Icon, label, corner = 'start', class: className, ...rest}: Props = $props();
</script>

<Tooltip tooltip={label} side="bottom" sideOffset={4} delayDuration={300}>
    {#snippet children({props})}
        <button
            {...mergeProps(props, rest, {class: ['content-corner-button', 'u-print-hidden', corner, className]})}
            type="button"
            aria-label={label}
        >
            <Icon size={18} strokeWidth={2} aria-hidden="true" />
        </button>
    {/snippet}
</Tooltip>

<style>
    .content-corner-button {
        position: absolute;
        /* The same inset as the sidebar's padding. */
        top: var(--space-4);
        /* Floats over the page content and its top fade (z-index 5, see
           SidebarContent), outside the nav. */
        --content-corner-button-z: 10;
        z-index: var(--content-corner-button-z);
        display: inline-flex;
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-nav-icon);
        cursor: pointer;
        transition: color var(--duration-fast);
    }

    .content-corner-button.start {
        left: var(--space-4);
    }

    .content-corner-button.end {
        right: var(--space-4);
    }

    .content-corner-button:hover {
        background: var(--color-hover);
        color: var(--color-text);
    }

    @media (--bp-md-and-smaller) {
        .content-corner-button :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
