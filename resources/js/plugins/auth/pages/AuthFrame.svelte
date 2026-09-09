<!--
  @component Shell for the authentication pages (login, keychain setup, unlock, recovery).

  Two panels: a narrow form panel on the left that holds the page content, and a
  canvas on the right. The canvas plays the deployment's background video (see
  `public/bg_videos/bg_videos.json`) with the creator credit; without a video it
  shows the encryption claim over an oversized, cropped HAWKI wordmark. The canvas
  collapses on small screens, where no video is loaded.

  Pages render their content as:
  ```svelte
  <div class="auth-intro"><h1 id="auth-title">…</h1><p class="auth-copy">…</p></div>
  <form class="auth-form">
      <div class="auth-field"><label for="x">…</label><Input id="x"/><small class="auth-hint">…</small></div>
  </form>
  ```
-->
<script lang="ts">
    import type { Snippet } from 'svelte';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import { useReducedMotion } from '$lib/utils/transitions/reducedMotion.svelte.js';
    import { pickLoginBackground, type LoginBackgroundVideo } from './loginBackground.js';
    import AuthPreferences from './AuthPreferences.svelte';

    interface Props {
        children: Snippet;
    }

    const { children }: Props = $props();
    const { __ } = useTranslator();
    const app = useApp();
    const theme = useStore('theme');
    const breakpoint = useBreakpoint();
    const reducedMotion = useReducedMotion();
    const connection = $derived(app.connection);
    const signedInAs = $derived(connection.hasUserInfo ? connection.userinfo.name : null);

    // The canvas is hidden on small screens; don't fetch a video nobody sees.
    const canvasVisible = $derived(!breakpoint.is('bpSmAndSmaller'));
    let video = $state<LoginBackgroundVideo | null>(null);
    let videoReady = $state(false);
    $effect(() => {
        if (!canvasVisible) return;
        const currentTheme = theme.theme;
        let cancelled = false;
        videoReady = false;
        void pickLoginBackground(app.config.get().transfer.baseUrl, currentTheme, {
            load: url => app.restApi.fetch(url, {credentials: 'omit'}),
            storage: app.localStorage
        }).then((picked) => {
            if (!cancelled) video = picked;
        });
        return () => { cancelled = true; };
    });
</script>

<div class="auth-page">
    <main id="main-content" tabindex="-1" class="auth-panel">
        <header class="auth-brand">
            <HawkLogo label={__('ui.auth.logoLabel')}/>
        </header>
        <section class="auth-body" aria-labelledby="auth-title">
            {@render children()}
        </section>
        <footer class="auth-footer">
            {#if signedInAs}
                <p class="auth-signed-in">{__('ui.auth.signedInAs', {name: signedInAs})}</p>
            {/if}
            <div class="auth-footer-row">
                <AuthPreferences/>
                {#if signedInAs}
                    <Button variant="ghost" size="sm" onclick={() => { void app.logout().catch(() => {}); }}>{__('ui.profile.logout')}</Button>
                {/if}
            </div>
        </footer>
    </main>
    <div class="auth-canvas" class:has-video={video !== null && videoReady}>
        <p class="auth-claim">{__('ui.auth.claim')}</p>
        <HawkLogo class="auth-canvas-mark" aria-hidden="true"/>
        {#if video && canvasVisible}
            {#key video.src}
                <!-- Ambient footage, no information: muted, no controls, and skipped by assistive tech. -->
                <video
                    class="auth-video"
                    src={video.src}
                    autoplay={!reducedMotion.current}
                    loop
                    muted
                    playsinline
                    disablepictureinpicture
                    preload="auto"
                    tabindex="-1"
                    aria-hidden="true"
                    oncanplay={() => { videoReady = true; }}
                    onerror={() => { video = null; }}
                ></video>
            {/key}
            <Link class="auth-credit" href={video.link} target="_blank">{__('ui.auth.videoCredit', {name: video.creator})}</Link>
        {/if}
    </div>
</div>

<style>
    /* ── Page ─────────────────────────────────────────────────────────── */
    .auth-page {
        box-sizing: border-box;
        min-height: 100dvh;
        display: grid;
        grid-template-columns: minmax(0, 26rem) minmax(0, 1fr);
        gap: var(--space-4);
        padding: var(--space-4);
        background: var(--color-bg-secondary);
    }

    /* ── Form panel ───────────────────────────────────────────────────── */
    .auth-panel {
        box-sizing: border-box;
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: var(--space-8);
        padding: clamp(var(--space-6), 4vw, var(--space-10));
        border-radius: var(--corner-lg);
        background: var(--color-surface-raised);
    }
    .auth-panel:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }
    .auth-brand :global(.mark) {
        height: 1.125rem;
    }
    .auth-body {
        align-self: center;
        display: grid;
        gap: var(--space-6);
        animation: auth-fade var(--duration-medium) var(--easing-out) both;
    }
    .auth-footer {
        display: grid;
        gap: var(--space-2);
    }
    .auth-signed-in {
        margin: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
    .auth-footer-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
    }

    /* ── Shared page content ──────────────────────────────────────────── */
    .auth-body :global(.auth-intro) {
        display: grid;
        gap: var(--space-2);
    }
    .auth-body :global(h1) {
        margin: 0;
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
        letter-spacing: -0.01em;
        text-wrap: balance;
    }
    .auth-body :global(.auth-copy) {
        margin: 0;
        color: var(--color-text-muted);
        line-height: var(--line-height-normal);
        text-wrap: pretty;
    }
    .auth-body :global(.auth-form) {
        display: grid;
        gap: var(--space-4);
    }
    .auth-body :global(.auth-form > .btn) {
        margin-top: var(--space-2);
    }
    .auth-body :global(.auth-field) {
        display: grid;
        gap: var(--space-1_5);
    }
    .auth-body :global(.auth-field label) {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }
    .auth-body :global(.auth-field .input) {
        background: var(--color-surface-light);
    }
    .auth-body :global(.auth-hint) {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }
    .auth-body :global(.auth-error),
    .auth-footer :global(.auth-error) {
        margin: 0;
        padding: var(--space-2) var(--space-3);
        border-radius: var(--corner-sm);
        background: color-mix(in oklab, var(--color-error) 9%, transparent);
        color: var(--color-error);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }
    .auth-body :global(.auth-field .auth-error) {
        padding: 0;
        background: none;
    }
    .auth-body :global(.auth-actions) {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
    }
    .auth-body :global(.auth-actions > .btn) {
        flex: 1 1 10rem;
    }

    /* ── Canvas ───────────────────────────────────────────────────────── */
    .auth-canvas {
        position: relative;
        overflow: hidden;
        display: grid;
        align-content: start;
        padding: clamp(var(--space-6), 4vw, var(--space-10));
        border-radius: var(--corner-lg);
        background: color-mix(in oklab, var(--color-accent-100) 55%, var(--color-bg-secondary));
    }
    :global(html.darkMode) .auth-canvas {
        background: color-mix(in oklab, var(--color-accent-fill) 45%, var(--color-bg-secondary));
    }
    .auth-claim {
        margin: 0;
        max-width: 24ch;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
        letter-spacing: -0.01em;
        text-wrap: balance;
    }
    /* The wordmark is set wider than the canvas and pushed below its bottom
       edge so the rounded corners crop it — the brand as material, not a badge. */
    .auth-canvas :global(.auth-canvas-mark) {
        position: absolute;
        left: -1.5%;
        bottom: 0;
        width: 103%;
        transform: translateY(26%);
        pointer-events: none;
        animation: auth-rise var(--duration-slow) var(--easing-spring) both;
    }
    .auth-canvas :global(.auth-canvas-mark .mark) {
        width: 100%;
        height: auto;
    }

    /* Video: fills the canvas and fades in over the wordmark once it can play,
       so a slow network never shows an empty panel. */
    .auth-video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0;
        transition: opacity var(--duration-medium) var(--easing-out);
        pointer-events: none;
    }
    .has-video .auth-video {
        opacity: 1;
    }
    .has-video .auth-claim,
    .has-video :global(.auth-canvas-mark) {
        visibility: hidden;
    }
    .auth-canvas :global(.auth-credit) {
        position: absolute;
        right: var(--space-4);
        bottom: var(--space-4);
        padding: var(--space-1) var(--space-2_5);
        border-radius: var(--corner-full);
        background: color-mix(in oklab, var(--color-surface-raised) 82%, transparent);
        backdrop-filter: blur(12px);
        color: var(--color-text);
        font-size: var(--font-size-xs);
        text-decoration: none;
        opacity: 0;
        transition: opacity var(--duration-medium) var(--easing-out);
    }
    .has-video :global(.auth-credit) {
        opacity: 1;
    }
    .auth-canvas :global(.auth-credit:hover),
    .auth-canvas :global(.auth-credit:focus-visible) {
        background: var(--color-surface-raised);
        opacity: 1;
    }

    /* ── Motion ───────────────────────────────────────────────────────── */
    @keyframes auth-fade {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes auth-rise {
        from { opacity: 0; transform: translateY(34%); }
        to { opacity: 1; transform: translateY(26%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .auth-body,
        .auth-canvas :global(.auth-canvas-mark) {
            animation: none;
        }
        .auth-video,
        .auth-canvas :global(.auth-credit) {
            transition: none;
        }
    }

    /* ── Small screens ────────────────────────────────────────────────── */
    @media (--bp-sm-and-smaller) {
        .auth-page {
            grid-template-columns: minmax(0, 1fr);
            padding: var(--space-3);
        }
        .auth-canvas {
            display: none;
        }
    }
</style>
