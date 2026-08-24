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
    import {isApple} from '$lib/utils/platform.js';
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
        onPress,
        children,
        ...restProps
    }: Props = $props();

    // `ctrl` means Command on Apple platforms, which the DOM reports as the
    // meta flag — so the required combination is matched against ctrlKey on
    // Windows/Linux and metaKey on Apple.
    const wantCtrl = $derived(ctrl && !isApple);
    const wantMeta = $derived(ctrl && isApple);

    // Which modifiers are held right now, mirrored from the last key event.
    // Reset on window blur — a keyup fired in another window never reaches us,
    // so a hint left behind by Alt-Tab would otherwise stick.
    let held = $state({ctrl: false, shift: false, alt: false, meta: false});

    function trackModifiers(event: KeyboardEvent) {
        held.ctrl = event.ctrlKey;
        held.shift = event.shiftKey;
        held.alt = event.altKey;
        held.meta = event.metaKey;
    }

    function resetModifiers() {
        held = {ctrl: false, shift: false, alt: false, meta: false};
    }

    // Exact match: extra held modifiers hide the hint too, so holding
    // Ctrl+Shift doesn't advertise the plain-Ctrl hotkeys alongside.
    const active = $derived(
        held.ctrl === wantCtrl && held.shift === shift && held.alt === alt && held.meta === wantMeta
    );

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
        trackModifiers(event);
        if (!onPress || event.repeat) return;
        if (
            event.ctrlKey !== wantCtrl ||
            event.shiftKey !== shift ||
            event.altKey !== alt ||
            event.metaKey !== wantMeta
        ) {
            return;
        }
        if (event.key.toLowerCase() !== key.toLowerCase()) return;
        // Bare-key hotkeys must not steal keystrokes from text entry; Shift
        // alone doesn't count as armor either (it just types capitals).
        if (!ctrl && !alt && isEditable(event.target)) return;
        event.preventDefault();
        onPress();
    }

    const label = $derived.by(() => {
        const keyLabel = key.length === 1 ? key.toUpperCase() : key;
        const parts: string[] = [];
        if (ctrl) parts.push(isApple ? '⌘' : 'Ctrl');
        if (alt) parts.push('Alt');
        if (shift) parts.push('Shift');
        parts.push(keyLabel);
        return parts.join(' + ');
    });

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

<svelte:window onkeydown={handleKeydown} onkeyup={trackModifiers} onblur={resetModifiers} />

{#if children}
    {@render children(KbdIndicator)}
{:else}
    <KbdIndicator {...restProps} />
{/if}
