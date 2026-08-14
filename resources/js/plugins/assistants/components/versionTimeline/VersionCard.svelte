<script lang="ts">
    let{
        version,
        date,
        note
    } = $props <{
        version: string;
        date: string;
        note: string;
    }>();
</script>


<div class="version-card">
    <div class="indicator">
        <div class="circle"></div>
        <div class="line"></div>
    </div>
    <div class="content">
        <div class="header">
            <span class="version">{version}</span>
            <span>.</span>
            <span class="date">{date}</span>
        </div>
        <p class="note">{note}</p>
    </div>
</div>


<style>
    .version-card {
        display: flex;
        flex-direction: row;
        width: 100%;
        height: fit-content;
    }
    .indicator{
        display: flex;
        flex-direction: column;
        align-items: center;
        align-content: center;
        width: 3rem;
        min-width: 3rem;
        /*padding-top: .5rem;*/
    }
    .indicator .circle {
        width: 10px;
        height: 10px;
        aspect-ratio: 1/1;
        border-radius: 50%;
        background-color: gray;
    }
    .indicator .line {
        width: 1px;
        background-color: gray;
        height: 100%;
    }
    .version-card:first-child .indicator .circle {
        background-color: var(--color-accent-500);
    }
    .version-card:last-child .indicator .line {
        display: none;
    }


    .content{
        margin-top: -5px;
        margin-bottom: 1.5rem;
        width: 90%;
        max-height: 5rem;
        /*overflow: hidden;*/
    }
    .header{
        display: flex;
        gap: .5rem;
        height: 1.5rem;
        padding: 0;
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--font-size-xs);
    }
    .version{
        font-weight: bold;
    }
    .note{
        font-size: var(--font-size-xs);
        padding: 0;
        margin: 0;
        color: var(--color-text-muted);
        text-overflow: ellipsis;
        overflow: hidden;
        max-height: calc(100% - 1.5rem);

        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3; /* number of lines before truncating */

    }

</style>