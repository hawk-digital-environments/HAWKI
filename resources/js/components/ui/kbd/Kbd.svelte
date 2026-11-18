<!--
  @component Global hotkey with an on-demand keycap hint. Listens on the window
  for `key` plus the given modifiers and calls `onPress` when the combination is
  hit. The `<kbd>` element itself only appears while all required modifiers are
  currently held down (and no others) — the listener is active regardless of
  visibility. With `alwaysVisible` the keycap stays rendered permanently. Either
  way it shows the full combination, e.g. `Ctrl + Shift + A`. `ctrl` means Ctrl
  on Windows/Linux and Command on Apple platforms — matching and label
  (`⌘ + Shift + A`) alike.

  `key` is matched against `KeyboardEvent.key` (case-insensitive). Hotkeys
  without ctrl/alt/meta are ignored while an input, textarea, select, or
  contenteditable element has focus, so plain letter shortcuts don't fire
  while typing.

  The keycap shows `key` as typed (single letters upper-cased); pass `label`
  to abbreviate it, e.g. `label="Esc"` for `key="Escape"`.

  Without `children` the keycap renders in place. Pass a `children` snippet to
  place it yourself: the snippet receives the keycap as an `Indicator`
  component, while `Kbd` keeps handling the listening and visibility.

  @example
  ```svelte
  <Kbd key="a" ctrl shift onPress={() => console.log('works!')} />

  <Kbd key="a" ctrl onPress={openDropdown}>
      {#snippet children(Indicator)}
          <Dropdown>
              <Indicator />
          </Dropdown>
      {/snippet}
  </Kbd>
  ```
-->
<script lang="ts">
    import {setContext, type Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {useKeyboard} from '$lib/components/ui/keyboard/KeyboardState.svelte.js';
    import KbdIndicator, {KBD_CONTEXT_KEY, type KbdContext} from './KbdIndicator.svelte';

    interface Props extends Omit<HTMLAttributes<HTMLElement>, 'children'> {
        /** The key to listen for, as reported by `KeyboardEvent.key`. */
        key: string;
        /** Require Ctrl (Command on Apple platforms) to be held. */
        ctrl?: boolean;
        /** Require Shift to be held. */
        shift?: boolean;
        /** Require Alt to be held. */
        alt?: boolean;
        /** Always render the keycap instead of only while the modifiers are
         * held. */
        alwaysVisible?: boolean;
        /** Text shown for the key on the keycap instead of `key` itself, e.g.
         * `Esc` for `Escape`. Matching still uses `key`. */
        label?: string;
        /** Called when the full combination is pressed. */
        onPress?: () => void;
        /** Custom placement of the keycap: receives it as an `Indicator`
         * component to render wherever it should sit. Without this, the
         * keycap renders in place. */
        children?: Snippet<[typeof KbdIndicator]>;
    }

    let {
        key,
        ctrl = false,
        shift = false,
        alt = false,
        alwaysVisible = false,
        label: keyText,
        onPress,
        children,
        ...restProps
    }: Props = $props();

    const keyboard = useKeyboard();

    const shortcut = $derived({key, ctrl, shift, alt});

    // Exact match: extra held modifiers hide the hint too, so holding
    // Ctrl+Shift doesn't advertise the plain-Ctrl hotkeys alongside.
    const active = $derived(keyboard.modifiersHeld(shortcut));

    function isEditable(target: EventTarget | null): boolean {
        return (
            target instanceof HTMLElement &&
            (target.isContentEditable ||
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT')
        );
    }

    function handleKeydown(event: KeyboardEvent) {
        if (!onPress || event.repeat) return;
        if (!keyboard.matches(event, shortcut)) return;
        // Bare-key hotkeys must not steal keystrokes from text entry; Shift
        // alone doesn't count as armor either (it just types capitals).
        if (!ctrl && !alt && isEditable(event.target)) return;
        event.preventDefault();
        onPress();
    }

    const label = $derived(keyboard.labelParts(shortcut, keyText).join(' + '));

    // The keycap itself is `KbdIndicator`, wired up through context so a
    // `children` snippet can drop it anywhere in its subtree.
    setContext<KbdContext>(KBD_CONTEXT_KEY, {
        get label() {
            return label;
        },
        get visible() {
            return alwaysVisible || active;
        }
    });
</script>

<svelte:window onkeydown={handleKeydown} />

{#if children}
    {@render children(KbdIndicator)}
{:else}
    <KbdIndicator {...restProps} />
{/if}
