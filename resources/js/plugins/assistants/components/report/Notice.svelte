<!--
  @component Quiet inline hint: a tone-colored icon and text on the flat
  surface fill used by the builder's footer bar and publish tiles. Use for
  contextual caveats above a form section (e.g. "changes here affect the
  review") where a full Alert would be too loud.
-->
<script lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons';

    interface Props {
        label: string;
        icon: IconComponent;
        tone?: 'neutral' | 'info' | 'warning' | 'error';
    }

    const {label, icon: Icon, tone = 'info'}: Props = $props();
</script>

<p class="notice" data-tone={tone}>
    <span class="icon" aria-hidden="true"><Icon size="1em"/></span>
    <span>{label}</span>
</p>

<style>
    .notice {
        display: flex;
        align-items: flex-start;
        gap: var(--space-2);
        margin: 0;
        padding: var(--space-2_5) var(--space-4);
        border-radius: var(--corner-md);
        background: var(--color-surface-light);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text);
    }
    /* As tall as one text line, so the icon centers on the first line. */
    .icon {
        display: flex;
        align-items: center;
        flex-shrink: 0;
        height: calc(var(--font-size-xs) * var(--line-height-normal));
        font-size: var(--font-size-sm);
    }
    [data-tone='neutral'] .icon { color: var(--color-text-muted); }
    [data-tone='info'] .icon { color: var(--color-info); }
    [data-tone='warning'] .icon { color: color-mix(in oklch, var(--color-warning) 80%, var(--color-text)); }
    [data-tone='error'] .icon { color: var(--color-error); }
</style>
