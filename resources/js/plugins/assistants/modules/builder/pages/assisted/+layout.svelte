<script lang="ts">

    let { children } = $props();


</script>



<div class="wrapper-grid">

    <div class="content-col">
        {@render children()}
    </div>
</div>