<!--
  @component Variant of `SnippetOrString` for use inside bits-ui trigger
  primitives (Tooltip, Popover, Select, …).

  **The problem it solves:** bits-ui trigger elements use a "child snippet"
  pattern where the primitive injects a `{props}` object that **must** be
  spread onto the root DOM element to wire up event handlers, ARIA attributes,
  and focus management. When the trigger content is a plain string, there is
  no snippet author to do the spreading — this component handles it
  automatically by rendering a `<button>` and spreading the props onto it.

  When `value` is a **string**, renders:

    <button type="button" {...snippetArgs.props}>Hello</button>

  When `value` is a **snippet**, delegates entirely to the snippet. The snippet
  receives the full `snippetArgs` object and is responsible for spreading
  `snippetArgs.props` onto its root element:

    {#snippet myTrigger({props})}
        <MyIcon {...props} />
    {/snippet}

  Typical usage inside a bits-ui trigger (e.g. Tooltip):

    <TooltipPrimitive.Trigger>
        {#snippet child(a)}
            <SnippetOrStringTrigger value={children} snippetArgs={a} />
        {/snippet}
    </TooltipPrimitive.Trigger>

  When `children` is the string `"Hover me"` the rendered output is:

    <button type="button" data-tooltip-trigger ...>Hover me</button>

  When `children` is a custom snippet it is called with `a` and must spread
  `a.props` itself.

  The `snippetArgs` type is intentionally `any` — bits-ui's internal trigger
  child argument type differs across primitives (Tooltip, Popover, Select) and
  is complex enough that casting it at every call site would be worse than
  accepting `any` here.
-->
<script lang="ts">

    import type {Snippet} from 'svelte';

    interface Props {
        /**
         * Content to render inside the trigger. A string produces a `<button>`
         * with the bits-ui props automatically applied. A snippet is called
         * with `snippetArgs` directly and must spread `snippetArgs.props` onto
         * its own root element.
         */
        value: string | Snippet<[any]> | undefined;

        /**
         * The argument object injected by the bits-ui trigger primitive (the
         * `a` in `{#snippet child(a)}`). Always contains at least a `props`
         * key whose value must be spread onto the trigger's root DOM element.
         *
         * `any` is intentional — the shape differs across primitives and
         * TypeScript's internal bits-ui types are too complex to re-declare
         * cleanly here.
         */
        snippetArgs?: any;
    }

    const {
        value,
        snippetArgs
    }: Props = $props();
</script>

{#if typeof value === "string"}
    <button type="button" {...(snippetArgs?.props ?? {})}>{value}</button>
{:else}
    {@render value?.(snippetArgs)}
{/if}
