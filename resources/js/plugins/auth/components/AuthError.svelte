<!--
  @component Inline auth error banner: a live region that takes focus when it appears,
  so the failure is announced. Pages keep their `{#if error}` guard and focus the
  banner through `bind:ref` from an effect.
-->
<script lang="ts">
    interface Props {
        /** Message to show and announce. */
        message: string;
        ref?: HTMLParagraphElement | null;
    }

    let { message, ref = $bindable(undefined) }: Props = $props();
</script>

<p class="auth-error" role="alert" tabindex="-1" bind:this={ref}>{message}</p>
