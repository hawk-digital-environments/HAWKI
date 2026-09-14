<!--
  @component Interactive icon button on a circular backdrop that only appears
  on hover — the affordance for icon-only actions (close, delete, confirm,
  trigger). The frosted variant keeps a persistent translucent backdrop for
  icons floating over imagery. The counterpart for non-interactive status
  indicators is StatusIcon, which always shows its backdrop.

  Usage — plain action icon:
    <ActionIcon icon={Delete02Icon} label={__('ui.common.remove')} onclick={() => remove()}/>

  Usage — as a bits-ui child/trigger (spread props are forwarded to the button):
    <ActionIcon icon={Cancel01Icon} label={__('ui.dialog.closeLabel')} size="sm" class="dialog-close" {...props}/>
-->
<script lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons';
    import type {HTMLButtonAttributes} from 'svelte/elements';

    interface Props {
        icon: IconComponent;
        /** Accessible name for the button; required unless rest props provide one. */
        label?: string;
        onclick?: HTMLButtonAttributes['onclick'];
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        /** `plain` shows its backdrop on hover; `frosted` keeps a translucent backdrop for use over imagery. */
        variant?: 'plain' | 'frosted';
        /** Accent-tinted wash for confirm-style affordances; `active` keeps the wash persistent. */
        tone?: 'inherit' | 'accent';
        /** Toggled-on state: persistent wash (accent) or filled icon (frosted). */
        active?: boolean;
        disabled?: boolean;
        class?: string;
        style?: string;
        /** Bindable reference to the rendered button element. */
        el?: HTMLButtonElement | null;
        [key: string]: any;
    }

    let {
        icon: Icon,
        label = undefined,
        onclick = undefined,
        size = 'md',
        variant = 'plain',
        tone = 'inherit',
        active = false,
        disabled = false,
        class: cls = undefined,
        style = undefined,
        el = $bindable(null),
        ...rest
    }: Props = $props();
</script>

<button
        bind:this={el}
        type="button"
        class="action-icon {cls}"
        style={style}
        data-variant={variant}
        data-tone={tone}
        data-size={size}
        data-active={active || undefined}
        aria-label={label}
        {disabled}
        {onclick}
        {...rest}
>
    <Icon size="1em"/>
</button>

<style>
    button {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 0;
        border: none;
        background: transparent;
        color: inherit;
        cursor: pointer;
        border-radius: var(--corner-full);
        opacity: 0.7;
        transition:
            opacity var(--duration-fast),
            background-color var(--duration-fast),
            color var(--duration-fast),
            transform var(--duration-fast);
    }
    button :global(svg) {
        display: block;
    }
    button:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    button:disabled {
        cursor: default;
        opacity: 0.4;
    }

    button[data-size='xs'] {
        width: 1.125rem;
        height: 1.125rem;
        font-size: var(--font-size-xs);
    }
    button[data-size='sm'] {
        width: var(--space-6);
        height: var(--space-6);
        font-size: var(--font-size-sm);
    }
    button[data-size='md'] {
        width: var(--space-8);
        height: var(--space-8);
        font-size: var(--font-size-lg);
    }
    button[data-size='lg'] {
        width: var(--space-10);
        height: var(--space-10);
        font-size: var(--font-size-xl);
    }
    button[data-size='xl'] {
        width: var(--space-16);
        height: var(--space-16);
        font-size: var(--font-size-2xl);
    }

    button[data-variant='plain']:hover:not(:disabled) {
        opacity: 1;
        background-color: color-mix(in oklch, currentColor 15%, transparent);
    }
    button[data-variant='plain']:active:not(:disabled) {
        transform: scale(0.92);
    }

    button[data-tone='accent'] {
        color: var(--color-accent-text);
    }
    button[data-tone='accent']:hover:not(:disabled) {
        background-color: var(--color-accent-100);
    }
    button[data-tone='accent'][data-active] {
        opacity: 1;
        background-color: var(--color-accent-100);
    }
    button[data-tone='accent'][data-active]:hover:not(:disabled) {
        background-color: var(--color-accent-200);
    }

    button[data-variant='frosted'] {
        opacity: 1;
        background-color: var(--action-icon-bg, oklch(100% 0 0 / 0.25));
        color: var(--action-icon-color, var(--color-text));
        backdrop-filter: blur(6px);
    }
    button[data-variant='frosted']:hover:not(:disabled) {
        background-color: var(--action-icon-hover-bg, oklch(100% 0 0 / 0.4));
        transform: scale(1.06);
    }
    button[data-variant='frosted'][data-active] :global(svg) {
        fill: currentColor;
    }
</style>
