<!--
  @component Selectable card used inside a `RadioCardGroup`.

  Renders its `children` alongside a radio indicator and reflects the group's
  current selection. Clicking selects it; can be disabled per-card or via the
  group. Reachable via keyboard with Space/Enter.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {Spring} from 'svelte/motion';
    import {getRadioCardContext} from '$lib/components/ui/radio-card/RadioCardContext.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this card represents within the group. */
        value: string;
        /** Disable this card. The group's disabled state also applies. */
        disabled?: boolean;
    }

    const {
        children,
        disabled: givenDisabled,
        value,
        class: className,
        ...restProps
    }: Props = $props();

    const ctx = getRadioCardContext();
    const disabled = $derived(givenDisabled || ctx.isDisabled);
    const checked = $derived(ctx.value === value);

    // Spring the dot in/out on selection for a snappy, springy feel.
    const dotScale = new Spring(0, {stiffness: 0.3, damping: 0.6});
    $effect(() => {
        dotScale.target = checked ? 1 : 0;
    });

    function select() {
        if (disabled) return;
        ctx.value = value;
    }

    function onkeydown(event: KeyboardEvent) {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            select();
        }
    }
</script>

<div
    {...mergeProps(
        {
            class: `radio-card${className ? ` ${className}` : ''}`,
            role: 'radio',
            'aria-checked': checked ? 'true' : 'false',
            'aria-disabled': disabled ? 'true' : undefined,
            'data-state': checked ? 'checked' : 'unchecked',
            'data-disabled': disabled ? '' : undefined,
            tabindex: disabled ? undefined : 0,
            onclick: select,
            onkeydown,
        },
        restProps,
    )}
>
    <span class="radio-card-indicator" aria-hidden="true">
        <span class="radio-card-dot" style="transform: scale({dotScale.current})"></span>
    </span>
    <div class="radio-card-body">{@render children?.()}</div>
    <input
        class="radio-card-input"
        type="radio"
        name={ctx.name}
        {value}
        {disabled}
        {checked}
        tabindex={-1}
        aria-hidden="true"
    />
</div>

<style>
    .radio-card {
        position: relative;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1, 0.25rem);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        background: var(--color-bg-secondary);
        border: 1px solid transparent;
        border-radius: var(--corner-full);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text);
        cursor: pointer;
        outline: none;
        user-select: none;
        transition:
            border-color var(--duration-fast, 150ms) var(--easing-default),
            background-color var(--duration-fast, 150ms) var(--easing-default);
    }

    .radio-card:hover {
        background: var(--color-hover);
    }

    .radio-card[data-state='checked'] {
        background: var(--color-highlight);
    }

    .radio-card:focus-visible {
        outline: 1px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: 1px;
    }

    .radio-card[data-disabled] {
        cursor: not-allowed;
        color: var(--color-text-disabled);
        opacity: 1;
    }

    .radio-card-body {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .radio-card-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: calc(0.25rem * 3.5);
        height: calc(0.25rem * 3.5);
        border: 1.5px solid var(--color-border-strong, var(--color-border));
        border-radius: var(--corner-full);
        transition: border-color var(--duration-fast, 150ms) var(--easing-default);
    }

    .radio-card[data-state='checked'] .radio-card-indicator {
        border-color: transparent;
    }

    .radio-card[data-disabled] .radio-card-indicator {
        border-color: var(--color-border);
    }

    .radio-card-dot {
        width: calc(0.25rem * 2.25);
        height: calc(0.25rem * 2.25);
        border-radius: var(--corner-full);
        background: var(--color-interactive);
    }

    /* Keeps the real radio in the form/a11y tree without showing it. */
    .radio-card-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>
