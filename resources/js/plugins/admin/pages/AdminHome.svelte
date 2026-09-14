<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import Page from '$lib/components/ui/page/Page.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Link from '$lib/components/util/link/Link.svelte';
    import { sections } from '../sections.js';
    const app = useApp();
    const { __ } = useTranslator();
</script>

<Page
    style="grid-template-columns: minmax(0, 1fr); min-width: 0"
    title={__('admin.title')}
>
    <div class="overview">
        <p class="intro">{__('admin.intro')}</p>
        {#each ['ai', 'people', 'system'] as group}
            {@const visible = sections.filter((section) => section.group === group && app.can(section.permission))}
            {#if visible.length}
                <section>
                    <h2>{__('admin.groups.' + group)}</h2>
                    <ul>
                        {#each visible as section}<li>
                                <Link
                                    class="section-link"
                                    href={{ name: `admin.${section.id}` }}
                                    ><span>{__('admin.sections.' + section.id)}</span><small
                                        >{__('admin.descriptions.' + section.id)}</small
                                    ></Link
                                >
                            </li>{/each}
                    </ul>
                </section>
            {/if}
        {/each}
        {#if !sections.some((section) => app.can(section.permission))}<p role="status">
                {__('admin.no_sections')}
            </p>{/if}
    </div>
</Page>

<style>
    .overview {
        max-width: 75rem;
        margin: auto;
        padding: var(--space-6);
    }
    .intro {
        font-size: var(--font-size-lg);
        max-width: 44rem;
        margin-bottom: var(--space-8);
    }
    section {
        margin-block: var(--space-8);
        display: grid;
        grid-template-columns: 12rem 1fr;
        gap: var(--space-4);
    }
    h2 {
        font-size: var(--font-size-lg);
        font-weight: 600;
    }
    ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0;
        margin: 0;
        list-style: none;
        border-top: var(--border);
    }
    li {
        border-bottom: var(--border);
    }
    .overview :global(.section-link) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        padding: var(--space-4);
        height: 100%;
        text-decoration: none;
        color: var(--color-text);
    }
    .overview :global(.section-link:hover) {
        background: var(--color-active-surface);
    }
    span {
        font-weight: 600;
    }
    small {
        color: var(--color-text-muted);
        line-height: 1.5;
    }
    @media (--bp-md-and-smaller) {
        section {
            grid-template-columns: 1fr;
        }
    }
    @media (--bp-sm-and-smaller) {
        ul {
            grid-template-columns: 1fr;
        }
    }
</style>
