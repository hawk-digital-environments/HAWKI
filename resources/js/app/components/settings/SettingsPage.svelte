<!--
  @component Frame for one settings section: the section's `SettingsGroup`s
  under a heading kept for assistive tech only — the settings nav already
  names the section on screen.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Section heading, announced to assistive tech. */
        title: string;
        /** The section's groups. */
        children: Snippet;
    }

    const {title, children}: Props = $props();

    const headingId = $props.id();
</script>

<section class="settings-page" aria-labelledby={headingId}>
    <h2 class="u-sr-only" id={headingId}>{title}</h2>
    {@render children()}
</section>

<style>
    .settings-page {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }
</style>
