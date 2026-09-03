<!--
  @component Container for a set of `RadioCard`s sharing a single selection.

  Bind `value` to the selected card's value; `onChange` fires on selection.
  Disabling the group disables (and dims) every card it contains.

  Keyboard: one card is in the Tab order (roving tabindex); ←/→/↑/↓ move the
  selection and focus, Home/End jump to the first/last enabled card. Name the
  group via `aria-labelledby` (preferred, pointing at a visible label) or
  `aria-label`.

  Publishes a `RadioCardContext` (see `RadioCardContext.svelte.ts`) that its
  `RadioCard` children read via `getRadioCardContext()` — always wrap
  `RadioCard`s in this group, never render them standalone.

  @example
  ```svelte
  <span id="variant-label">Variant</span>
  <RadioCardGroup value={variant} onChange={(v) => (variant = v)} name="tool-variant" aria-labelledby="variant-label">
      <RadioCard value="auto">Auto</RadioCard>
      <RadioCard value="native">Native</RadioCard>
  </RadioCardGroup>
  ```
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createRadioCardContext} from './RadioCardContext.svelte.js';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'onchange'> {
        /** The selected value. Leave unset for an uncontrolled group. */
        value?: string;
        /** Disable every card in the group. */
        disabled?: boolean;
        /** Shared `name` applied to each card's radio input. */
        name?: string;
        /** Called with the newly selected value. */
        onChange?: (newValue: string) => void;
    }

    let {
        value = $bindable(''),
        children,
        onChange,
        disabled = false,
        name,
        class: className,
        ...restProps
    }: Props = $props();

    function select(newValue: string) {
        if (value === newValue) return;
        value = newValue;
        onChange?.(newValue);
    }

    createRadioCardContext(() => value, select, () => disabled, () => name);

    /** Moves selection + focus between the enabled radios (WAI-ARIA radio group pattern). */
    function onkeydown(event: KeyboardEvent) {
        const group = event.currentTarget as HTMLElement;
        const origin = (event.target as HTMLElement | null)?.closest<HTMLElement>('[role="radio"]');
        if (!origin) return;

        const radios = Array.from(
            group.querySelectorAll<HTMLElement>('[role="radio"]:not([aria-disabled="true"])')
        );
        const index = radios.indexOf(origin);
        if (index < 0 || radios.length === 0) return;

        let next: number;
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                next = (index + 1) % radios.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                next = (index - 1 + radios.length) % radios.length;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = radios.length - 1;
                break;
            default:
                return;
        }
        event.preventDefault();
        // Keep enclosing menus (bits-ui roving focus) from acting on the same key.
        event.stopPropagation();
        const target = radios[next];
        target.focus();
        const nextValue = target.dataset.value;
        if (nextValue !== undefined) select(nextValue);
    }
</script>

<div
    {...mergeProps(
        {
            class: `radio-card-group${className ? ` ${className}` : ''}`,
            role: 'radiogroup',
            'aria-disabled': disabled ? 'true' : undefined,
            'data-disabled': disabled ? '' : undefined,
            onkeydown,
        },
        restProps,
    )}
>
    {@render children?.()}
</div>

<style>
    .radio-card-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-1, 0.25rem);
    }

    .radio-card-group[data-disabled] {
        opacity: 0.5;
        pointer-events: none;
    }
</style>
