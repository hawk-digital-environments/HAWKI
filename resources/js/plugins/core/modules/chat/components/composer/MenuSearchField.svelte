<!--
  @component The one-line filter field shown at the top of the composer's `ToolMenu` and
  `AssistantMenu`. Purely presentational: it owns nothing but its text, which the menus
  bind to and use to filter their own entries.

  It lives *outside* the `DropdownMenuDetailView` viewport so it stays anchored while the
  list/detail panels slide, and it swallows the keystrokes that bits-ui's menu would
  otherwise consume as typeahead — arrows, Enter, Tab and Escape still bubble, so the
  keyboard flow of the menu is unchanged.

  ## Usage
  ```svelte
  <MenuSearchField bind:value={query} placeholder={__('…searchPlaceholder')}/>
  ```
-->
<script lang="ts">
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';

    interface Props {
        /** The current filter text. Supports bind:value. */
        value?: string;
        /** Placeholder and accessible label of the field. */
        placeholder: string;
    }

    let {value = $bindable(''), placeholder}: Props = $props();

    let inputEl = $state<HTMLInputElement | null>(null);

    // Keys the menu itself owns: they must reach the content so arrowing out of the field
    // into the rows, selecting and closing keep working.
    const MENU_KEYS = ['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'];

    function onkeydown(event: KeyboardEvent) {
        if (MENU_KEYS.includes(event.key)) {
            return;
        }
        // Everything else is typing: keep it out of bits-ui's typeahead, which would move
        // focus to a matching row mid-word.
        event.stopPropagation();
    }

    // The field mounts with the menu, and bits-ui focuses the content on open — so claim
    // focus back after that, letting the user type straight into the filter.
    $effect(() => {
        const frame = requestAnimationFrame(() => inputEl?.focus());
        return () => cancelAnimationFrame(frame);
    });
</script>

<div class="menu-search">
    <Search01Icon class="menu-search-icon" size={16}/>
    <input
        bind:this={inputEl}
        bind:value
        type="text"
        autocomplete="off"
        spellcheck="false"
        aria-label={placeholder}
        placeholder={placeholder}
        onkeydown={onkeydown}/>
</div>

<style>
    .menu-search {
        display: flex;
        padding: var(--space-3);
        /* Tighter below, so the field sits closer to the rows it filters than to the
           menu's top edge. */
        padding-bottom: var(--space-1);
        gap: var(--space-2);
        align-items: center;
        background: none;
        border: none;
        flex: 0 0 auto;
    }

    /*
      The legacy global stylesheet (`public/css/style.css`) still gives every bare `input`
      a border, a min-width/height and padding; those are undone here. Its background is
      a normal `@layer legacy` declaration, so this unlayered rule simply overrides it.
    */
    .menu-search input {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 0;
        height: auto;
        border: none;
        border-radius: 0;
        outline: none;
        background: transparent;
        color: var(--color-text);
        font-size: var(--font-size-xs);
        font-family: inherit;
        padding: 0;
        box-shadow: none;
    }

    .menu-search :global(.menu-search-icon) {
        flex: 0 0 auto;
        color: var(--color-text-muted, var(--color-text));
        opacity: 0.7;
    }

    .menu-search input::placeholder {
        color: var(--color-text-muted, var(--color-text));
        opacity: 0.7;
    }
</style>
