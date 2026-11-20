<!--
  @component Non-interactive status indicator icon on a persistent tinted,
  rounded-square backdrop — leading file/item glyphs, warning states, empty
  states. The counterpart for interactive icons is ActionIcon, which only
  shows its backdrop on hover.

  Usage:
    <StatusIcon icon={File01Icon}/>
    <StatusIcon icon={Alert02Icon} tone="warning" size="xs"/>
-->
<script lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons';

    interface Props {
        icon: IconComponent;
        tone?: 'accent' | 'info' | 'success' | 'warning' | 'error' | 'neutral';
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        class?: string;
        style?: string;
        [key: string]: any;
    }

    let {
        icon: Icon,
        tone = 'accent',
        size = 'md',
        class: cls = undefined,
        style = undefined,
        ...rest
    }: Props = $props();
</script>

<span
        class="status-icon {cls}"
        style={style}
        data-tone={tone}
        data-size={size}
        {...rest}
>
    <Icon size="1em"/>
</span>

<style>
    span {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: var(--corner-sm);
    }
    span :global(svg) {
        display: block;
    }

    span[data-size='xs'] {
        width: var(--space-5);
        height: var(--space-5);
        font-size: var(--font-size-xs);
    }
    span[data-size='sm'] {
        width: var(--space-6);
        height: var(--space-6);
        font-size: var(--font-size-sm);
    }
    span[data-size='md'] {
        width: var(--space-8);
        height: var(--space-8);
        font-size: var(--font-size-lg);
    }
    span[data-size='lg'] {
        width: var(--space-10);
        height: var(--space-10);
        font-size: var(--font-size-lg);
    }
    span[data-size='xl'] {
        width: var(--space-16);
        height: var(--space-16);
        font-size: var(--font-size-2xl);
    }

    span[data-tone='accent'] {
        background-color: var(--color-surface-light);
        color: var(--color-text-muted);
    }
    span[data-tone='info'] {
        background-color: color-mix(in oklch, var(--color-info) 12%, transparent);
        color: color-mix(in oklch, var(--color-info) 75%, var(--color-text));
    }
    span[data-tone='success'] {
        background-color: color-mix(in oklch, var(--color-success) 12%, transparent);
        color: color-mix(in oklch, var(--color-success) 70%, var(--color-text));
    }
    span[data-tone='warning'] {
        background-color: color-mix(in oklch, var(--color-warning) 14%, transparent);
        color: color-mix(in oklch, var(--color-warning) 70%, var(--color-text));
    }
    span[data-tone='error'] {
        background-color: color-mix(in oklch, var(--color-error) 12%, transparent);
        color: color-mix(in oklch, var(--color-error) 75%, var(--color-text));
    }
    span[data-tone='neutral'] {
        background-color: var(--color-surface-light);
        color: var(--color-text-muted);
    }
</style>
