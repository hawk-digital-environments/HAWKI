<!--
  @component Renders a value that is either a plain string or a Svelte snippet.
  Eliminates the `{#if typeof x === 'string'}…{:else}…{/if}` boilerplate that
  would otherwise repeat in every component that accepts a string-or-snippet
  prop (labels, descriptions, content areas, etc.).

  Plain string (rendered as inline text with no wrapper element):

    <SnippetOrString value="Hello world" />

  Snippet with no arguments:

    {#snippet greeting()}
        <strong>Hello!</strong>
    {/snippet}
    <SnippetOrString value={greeting} />

  Snippet that receives an argument via `snippetArgs`:

    {#snippet label(name: string | undefined)}
        <em>{name}</em>
    {/snippet}
    <SnippetOrString value={label} snippetArgs="Alice" />

  The generic type `T` is inferred from the `snippetArgs` you pass, so the
  snippet argument type is checked automatically. When no argument is needed,
  omit `snippetArgs` — the snippet receives `undefined`.

  For trigger elements that must spread bits-ui's `{props}` object, use
  `SnippetOrStringTrigger` instead.
-->
<script lang="ts" generics="T">
    import type {Snippet} from 'svelte';

    interface Props {
        /**
         * The value to render. Strings are output as-is (no wrapper element).
         * Snippets are called with `snippetArgs` as the first argument.
         */
        value: string | Snippet<[T | undefined]> | undefined;

        /**
         * Argument forwarded to the snippet when `value` is a snippet.
         * The snippet receives this as its first (and only) parameter.
         * Example: `snippetArgs="Alice"` → snippet called as `snippet("Alice")`.
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
