<script lang="ts">
    import type {Component} from 'svelte';
    import DefaultLayout from '$lib/components/layout/DefaultLayout.svelte';

    interface Props {
        layout?: Component;
        component: Component;
        componentProps?: any;
    }

    const {
        layout: Layout,
        component: ComponentToRender,
        componentProps = {}
    }: Props = $props();
</script>

{#if Layout}
    <Layout>
        <ComponentToRender {...componentProps}/>
    </Layout>
{:else}
    <DefaultLayout>
        <ComponentToRender {...componentProps}/>
    </DefaultLayout>
{/if}
