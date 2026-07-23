<script lang="ts">

    import type {Snippet} from 'svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        children: Snippet;
    }

    const {children}: Props = $props();
</script>
<section class="message-citations">
    <h3 class="message-citations__heading">{__('chat.message.sources')}</h3>
    <div class="message-citations__grid">
        {@render children?.()}
    </div>
</section>

<style>
    .message-citations {
        margin-block-start: var(--space-6);
    }

    .message-citations__heading {
        margin-block: 0 var(--space-3);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
    }

    .message-citations__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-2);
    }

    @media (--bp-md-and-smaller) {
        .message-citations__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (--bp-xs) {
        .message-citations__grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
