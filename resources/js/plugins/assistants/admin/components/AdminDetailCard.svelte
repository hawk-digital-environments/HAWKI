<!--
  @component Plain labelled card, grouping one section of the assistant
  detail page (General / Behaviour / Knowledge / Model / ...). Local to this
  page — not promoted to a shared primitive since nothing else uses it yet.
-->
<script lang="ts">
    import type { Snippet } from 'svelte';

    const { title, children }: { title: string; children: Snippet } = $props();
</script>

<section class="admin-detail-card">
    <h2>{title}</h2>
    <div class="body">
        {@render children()}
    </div>
</section>

<style>
    .admin-detail-card {
        border: var(--border);
        border-radius: var(--corner-lg);
        background: var(--color-surface-raised);
        padding: var(--space-5);
    }
    h2 {
        margin: 0 0 var(--space-4);
        font-size: var(--font-size-md);
        font-weight: var(--font-weight-medium, 500);
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }
</style>
