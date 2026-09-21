<!--
  @component Inline HAWKI wordmark rendered as an SVG with an accessible label.
  Sized through the surrounding font-size/line-height, coloured via `currentColor`.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    /** @todo Load the deployment-specific wordmark from frontend configuration. */
    interface Props extends HTMLAttributes<HTMLSpanElement> {
        /** Accessible label for the wordmark. */
        label?: string;
    }

    const {label = 'hawki', class: className, ...restProps}: Props = $props();
</script>

<span {...restProps} class={["logo", className]} role="img" aria-label={label}>
    <svg class="mark" viewBox="0 0 330 75" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M38.4196 38.37C38.4196 29.41 34.9996 28.12 30.2896 28.12C24.3996 28.12 22.1596 29.53 20.5096 35.66V73.96C15.6796 74.55 4.94963 74.55 0.119629 73.96V1.38C4.94963 0.79 15.6796 0.79 20.5096 1.38V10.92L19.5696 21.05H20.3996C22.3996 13.51 29.4696 10.92 39.0196 10.92C50.4496 10.92 58.8196 15.4 58.8196 28.48V73.97C53.9896 74.56 43.1496 74.56 38.4296 73.97V38.38L38.4196 38.37Z" fill="currentColor"/>
        <path d="M108.07 64.77H107.24C105.47 71.96 98.2795 74.79 89.0895 74.79C78.0095 74.79 69.5295 69.84 69.5295 57.11C69.5295 40.26 78.5995 36.72 91.4495 36.72C97.4595 36.72 101.7 37.07 107.12 37.78V35.66C107.12 30.12 103.59 26.94 91.9195 26.94C87.4395 26.94 81.6695 27.41 74.5995 28.59C74.0095 23.76 74.0095 17.87 74.5995 13.03C83.4395 11.38 90.6295 10.91 98.2895 10.91C117.5 10.91 127.52 15.86 127.52 31.65V73.96C122.69 74.55 113.85 74.55 109.14 73.96L108.08 64.77H108.07ZM107.13 49.33C104.07 48.62 100.77 48.27 98.6495 48.27C93.1095 48.27 89.9295 48.98 89.9295 54.4C89.9295 59.82 93.1095 60.17 97.1195 60.17C101.83 60.17 105.13 58.76 107.14 54.99V49.33H107.13Z" fill="currentColor"/>
        <path d="M182.67 38.25L175.01 73.96C168.65 74.55 156.16 74.55 149.79 73.96L134.47 12.68C139.18 12.09 150.03 12.09 154.74 12.68L163.81 55.57L172.41 12.68C177.48 12.09 188.2 12.09 193.15 12.68L201.63 55.57L210.82 12.68C215.65 12.09 226.26 12.09 230.97 12.68L215.65 73.96C209.29 74.55 196.8 74.55 190.31 73.96L182.65 38.25H182.67Z" fill="currentColor"/>
        <path d="M264.45 48.39L259.97 49.1V73.96C255.14 74.55 244.41 74.55 239.58 73.96V1.38C244.41 0.79 255.14 0.79 259.97 1.38V31.78L265.15 30.95L279.06 12.68C283.66 12.09 296.26 12.09 300.86 12.68L281.42 39.08L301.22 73.96C296.62 74.55 283.78 74.55 279.07 73.96L264.46 48.39H264.45Z" fill="currentColor"/>
        <path d="M309.59 12.68C314.42 12.09 325.15 12.09 329.98 12.68V73.96C325.15 74.55 314.42 74.55 309.59 73.96V12.68Z" fill="currentColor"/>
    </svg>
</span>

<style>
    .logo {
        display: inline-flex;
        align-items: center;
        min-width: 0;
        user-select: none;
    }

    .mark {
        height: 0.85rem;
        width: auto;
        color: var(--color-accent-text, #2f2abf);
    }
</style>
