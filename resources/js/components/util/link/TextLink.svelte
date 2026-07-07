<!--
  @component Plain text link built on `Link.svelte`. For external links the
  target site's favicon is automatically prepended to the content — the
  classic "text link with icon" case. Use `Link.svelte` directly for layout
  links (cards, tiles, …) where the favicon needs to sit somewhere specific.

    <TextLink href="https://example.com" target="_blank">example.com</TextLink>

  Disable the favicon while keeping all other Link behavior:

    <TextLink href="https://example.com" favicon={false}>No icon</TextLink>
-->
<script lang="ts">
    import type {ComponentProps, Snippet} from 'svelte';
    import Link from '$lib/components/util/link/Link.svelte';

    interface Props extends Omit<ComponentProps<typeof Link>, 'children'> {
        /**
         * When true (default) and `href` points to an external origin, the
         * target site's favicon is prepended to the link content.
         */
        favicon?: boolean;
        /** Link content. */
        children?: Snippet;
    }

    const {
        favicon = true,
        children: content,
        ...restProps
    }: Props = $props();
</script>

<Link {...restProps}>
    {#snippet children({favicon: renderFavicon})}
        {#if favicon}
            {@render renderFavicon()}
        {/if}
        {@render content?.()}
    {/snippet}
</Link>
