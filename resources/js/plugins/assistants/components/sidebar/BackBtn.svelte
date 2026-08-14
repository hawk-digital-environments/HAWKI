<script lang="ts">

    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import { goto } from '$app/navigation';

    let{ label, href } = $props<{
        label: string,
        href: string
    }>();

    function onclick(){
        goto(href);
    }
</script>

<button class="back-btn" {onclick}>
    <span class="icon"><ArrowLeft01Icon size="1em" /></span>
    <span>{label}</span>
</button>

<style>
    button{
        display: flex;
        align-items: center;
        column-gap: .5rem;
        padding: .5rem 0;
        color: var(--text-color);
        fill: var(--text-color);
        font-size: var(--font-xs);
        opacity: 0.7;
        transition: opacity var(--transition-fast);
        user-select: none;
        margin-top: .5rem;
    }
    button:hover{
        opacity: 1;
    }
    .icon{
        width: 1rem;
        font-size: 1rem;
    }
</style>

