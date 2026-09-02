<!--
  @component A titled group of menu items that folds away. Pairs a `collapsible`
  `DropdownMenuLabel` with the rows underneath it, so the disclosure and the thing it
  discloses are one component — the label reports the toggle, this collapses the body with a
  `growTransition` instead of having it blink out of existence.

  The state is the caller's: pass `expanded` (or `bind:expanded`) and, when the caller keeps
  the state itself — a set of collapsed section keys, say — handle `onToggle`. Sections
  space themselves apart, so a menu can put them back to back without separators.

  The transition is local, so it only plays when a section is actually folded or unfolded,
  never when the menu itself opens. Unfolding swings 5% past its height before settling — a
  section is a block of rows arriving, worth a small spring, where the default
  grow would just slide. Folding stays a plain ease: a swing on the way out would take the
  height below zero and only clip.

  ```svelte
  {#each providers as provider (provider.label)}
      <DropdownMenuSection
          label={provider.label}
          expanded={isExpanded(provider.label)}
          onToggle={() => toggleSection(provider.label)}>
          {#each provider.models as model (model.model_id)}
              <DropdownMenuRadioItem value={model.model_id}>{model.label}</DropdownMenuRadioItem>
          {/each}
      </DropdownMenuSection>
  {/each}
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';

    interface Props {
        /** The section's heading, rendered as the collapsible label. */
        label: Snippet | string;
        /** Whether the section is open. Supports bind:expanded. @defaultValue true */
        expanded?: boolean;
        /** Called with the section's new state when its label is activated. */
        onToggle?: (expanded: boolean) => void;
        /** The rows this section holds. */
        children?: Snippet;
    }

    let {
        label,
        expanded = $bindable(true),
        onToggle,
        children
    }: Props = $props();

    function toggle(next: boolean) {
        expanded = next;
        onToggle?.(next);
    }
</script>

<div class="dropdown-section">
    <DropdownMenuLabel collapsible expanded={expanded} onToggle={toggle}>
        <SnippetOrString value={label}/>
    </DropdownMenuLabel>

    {#if expanded}
        <!--
          Tension picked for a 5% peak: `growTransition`'s enter curve tops out at
          4s³ / 27(s+1)² over its natural size, which puts s at ~1.165. Split in/out, because
          `growTransition` reads its direction from the params: a single `transition:` would
          take the enter's swinging curve out with it, where the leave wants a plain ease.

          For the menu to move with this rather than clamping partway through, whatever holds
          the section must be free to size to its content — see `DropdownMenu`'s `maxHeight`.
        -->
        <div
            in:growTransition={{overshoot: 1.165}}
            out:growTransition={{direction: 'out'}}>
            {@render children?.()}
        </div>
    {/if}
</div>

<style>
    /* Sections carry their own separation, so a menu built from them needs no separators. */
    .dropdown-section + .dropdown-section {
        margin-top: var(--space-1_5);
    }
</style>
