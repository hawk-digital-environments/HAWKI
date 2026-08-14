<!--@deprecated -->


<script lang="ts">
    import type {Snippet} from "svelte";
    import BackBtn from "$lib/components/sidebar/BackBtn.svelte";
    import StatusCard from "$plugins/assistants/components/report/StatusCard.svelte";

    let {
        children,
        status,
        title,
        backTo

    } = $props<{
        children: Snippet;
        status?: Snippet;
        title?: string;
        backTo?: {
            label: string;
            href: string;
        };
    }>();

</script>
<div class="sidebar">
    {#if backTo}
        <div class="back-btn-wrapper">
            <BackBtn label={backTo.label} href={backTo.href}/>
        </div>
    {/if}

    <div class="sidebar-wrapper">
        {#if status}
            {@render status()}
        {/if}

        {#if title}
            <p class="title">{title}</p>
        {/if}
        <div class="content">
            {@render children()}
        </div>
    </div>
</div>


<style>
    .sidebar {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        row-gap: 0.5rem;
        min-height: 0;          /* ← allows flex child to shrink below content size */
        max-height: 100vh;      /* ← cap at viewport height */
    }

    .sidebar-wrapper {
        padding: 0 1rem;

        width: 100%;
        min-height: 0;          /* ← same trick for nested flex child */
        overflow-y: auto;
    }

    .content {
        display: flex;
        flex-direction: column;
        row-gap: .5rem;
    }

    .title {
        font-weight: bold;
        margin: .5rem 0;
        padding: .5rem 0;
    }
    .back-btn-wrapper{
        padding: 0 1rem;
    }
</style>
