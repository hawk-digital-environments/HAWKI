<script lang="ts" generics="T">
    import type {Snippet} from 'svelte';

    interface Props {
        /**
         * The value to render, which can be either a string or a snippet.
         * If a snippet is provided, the snippet will be rendered with the provided arguments (snippetArgs)
         */
        value: string | Snippet<[T | undefined]> | undefined;

        /**
         * Allows the outside world to pass additional arguments if a snippet is provided.
         * The snippet will receive the given value as the first argument
         */
        snippetArgs?: T;
    }

    const {
        value,
        snippetArgs
    }: Props = $props();
</script>

{#if typeof value === "string"}
    {value}
{:else}
    {@render value?.(snippetArgs)}
{/if}
