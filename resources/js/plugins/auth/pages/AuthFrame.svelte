<!--
  @component Shell for the authentication pages (login, keychain setup, unlock, recovery).

  Two layouts. With `canvas` (the login page) the page splits into a narrow form
  panel on the left and a canvas on the right that shows the background an
  administrator configured (`branding.loginBackgrounds`, by default the videos in
  `public/bg_videos`), one per visit in turn; an image or a video. Behind it, or
  without one, the canvas shows the encryption claim over an
  oversized, cropped wordmark, or the deployment's own logo in its place. The
  canvas collapses on small screens, where nothing is loaded. When the background
  is a video, a switch in the canvas corner turns it off for good on low-power
  devices, remembered in `localStorage` as `hawki.auth.backgroundVideo`.

  Without `canvas` (every page after the login) the content sits in a single
  centered column on the plain page background: wordmark top-left, content in the
  middle of the viewport, preferences bottom-left and the signed-in account with
  logout bottom-right.

  Both layouts end with the operator's legal links (imprint, privacy policy,
  terms of use, accessibility statement) and the operating institution, as far
  as an administrator configured them.

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
    import BrandLogo from '$lib/app/components/branding/BrandLogo.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useConfig } from '$lib/app/hooks/useConfig.svelte.js';
    import { useLegalLinks } from '$lib/app/hooks/useLegalLinks.svelte.js';
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import { useReducedMotion } from '$lib/utils/transitions/reducedMotion.svelte.js';
    import {
        isLoginVideoEnabled,
        pickLoginBackground,
        setLoginVideoEnabled,
        type LoginBackground
    } from './loginBackground.js';
    import AuthPreferences from './AuthPreferences.svelte';

    interface Props {
        /** Split layout with the video canvas beside the form panel (login only). */
        canvas?: boolean;
        children: Snippet;
    }

    const { canvas = false, children }: Props = $props();
    const { __ } = useTranslator();
    const app = useApp();
    const config = useConfig();
    const branding = $derived(config.branding);
    const legalLinks = useLegalLinks();
    const links = $derived(legalLinks());
    const breakpoint = useBreakpoint();
    const reducedMotion = useReducedMotion();
    const connection = $derived(app.connection);
    const signedInAs = $derived(connection.hasUserInfo ? connection.userinfo.name : null);

    // The canvas is hidden on small screens; don't fetch a background nobody sees.
    const canvasVisible = $derived(canvas && !breakpoint.is('bpSmAndSmaller'));
    let videoEnabled = $state(isLoginVideoEnabled(app.localStorage));
    $effect(() => {
        setLoginVideoEnabled(app.localStorage, videoEnabled);
    });
    // Advances the rotation, so it runs once per visible canvas, not on every render.
    let picked = $state<LoginBackground | null>(null);
    $effect(() => {
        const backgrounds = branding?.loginBackgrounds ?? [];
        picked = canvasVisible ? pickLoginBackground(backgrounds, app.localStorage) : null;
    });
    /** The energy switch only makes sense for a video. */
    const playsVideo = $derived(picked?.kind === 'video');
    const background = $derived(picked && (picked.kind === 'image' || videoEnabled) ? picked : null);
    /** Which background has loaded; the canvas fades it in only then. */
    let loaded = $state<string | null>(null);
    const backgroundReady = $derived(background !== null && loaded === background.url);
</script>

<div class="auth-page" class:canvas>
    <div class="auth-panel">
        <header class="auth-brand">
            <BrandLogo/>
        </header>
        <main id="main-content" tabindex="-1" class="auth-main">
            <section class="auth-body" aria-labelledby="auth-title">
                {@render children()}
            </section>
        </main>
        <footer class="auth-footer">
            <AuthPreferences/>
            {#if signedInAs}
                <div class="auth-account">
                    <p class="auth-signed-in">{__('ui.auth.signedInAs', {name: signedInAs})}</p>
                    <Button variant="ghost" size="sm" onclick={() => { void app.logout().catch(() => {}); }}>{__('ui.profile.logout')}</Button>
                </div>
            {/if}
            {#if links.length || branding?.organizationName}
                <nav class="auth-legal" aria-label={__('ui.legal.label')}>
                    {#if branding?.organizationName}
                        {#if branding.organizationUrl}
                            <Link href={branding.organizationUrl} target="_blank">{__('ui.legal.operatedBy', {name: branding.organizationName})}</Link>
                        {:else}
                            <span>{__('ui.legal.operatedBy', {name: branding.organizationName})}</span>
                        {/if}
                    {/if}
                    {#each links as link (link.key)}
                        <Link href={link.href} target="_blank">{link.label}</Link>
                    {/each}
                </nav>
            {/if}
        </footer>
    </div>
    {#if canvas}
        <div class="auth-canvas" class:has-video={backgroundReady} class:has-switch={playsVideo} class:has-credit={!!background?.credit}>
            <p class="auth-claim">{__('ui.auth.claim')}</p>
            <BrandLogo class="auth-canvas-mark" aria-hidden="true"/>
            {#if playsVideo}
                <label class="auth-video-toggle" title={__('ui.auth.videoToggleHint')}>
                    <span id="auth-video-toggle-label">{__('ui.auth.videoToggle')}</span>
                    <Switch bind:checked={videoEnabled} aria-labelledby="auth-video-toggle-label"/>
                </label>
            {/if}
            {#if background}
                {#key background.url}
                    {#if background.kind === 'image'}
                        <!-- Decoration, no information: skipped by assistive tech. -->
                        <img
                            class="auth-video"
                            src={background.url}
                            alt=""
                            onload={() => { loaded = background.url; }}
                        />
                    {:else}
                    <!-- Ambient footage, no information: muted, no controls, and skipped by assistive tech. -->
                    <video
                        class="auth-video"
                        src={background.url}
                        autoplay={!reducedMotion.current}
                        loop
                        muted
                        playsinline
                        disablepictureinpicture
                        preload="auto"
                        tabindex="-1"
                        aria-hidden="true"
                        oncanplay={() => { loaded = background.url; }}
                    ></video>
                    {/if}
                {/key}
                {#if background.credit}
                    {#if background.link}
                        <Link class="auth-credit" href={background.link} target="_blank">© {background.credit}</Link>
                    {:else}
                        <span class="auth-credit">© {background.credit}</span>
                    {/if}
                {/if}
            {/if}
        </div>
    {/if}
</div>

<style>
    /* ── Page: centered column (default) ──────────────────────────────── */
    .auth-page {
        --auth-column: 26rem;
        box-sizing: border-box;
        min-height: 100dvh;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        background: var(--color-bg);
    }
    .auth-panel {
        box-sizing: border-box;
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: var(--space-8);
        padding: clamp(var(--space-5), 4vw, var(--space-10));
    }
    /* A step larger than in the sidebar, by the same factor for either kind of logo. */
    .auth-brand :global(.mark) {
        height: calc(var(--logo-height, 0.875rem) * 9 / 7);
    }
    .auth-brand :global(.mark.image) {
        height: calc(var(--logo-height, 1.75rem) * 9 / 7);
    }
    .auth-main {
        display: grid;
        justify-items: center;
        align-content: center;
    }
    .auth-main:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }
    .auth-body {
        box-sizing: border-box;
        width: 100%;
        max-width: var(--auth-column);
        display: grid;
        gap: var(--space-6);
        animation: auth-fade var(--duration-medium) var(--easing-out) both;
    }
    .auth-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2) var(--space-4);
    }
    .auth-account {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-2);
    }
    .auth-signed-in {
        margin: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
    .auth-legal {
        flex-basis: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1) var(--space-4);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
    .auth-legal :global(a) {
        color: inherit;
        text-decoration: none;
    }
    .auth-legal :global(a:hover),
    .auth-legal :global(a:focus-visible) {
        color: var(--color-text);
        text-decoration: underline;
    }

    /* ── Page: split layout with canvas (login) ───────────────────────── */
    .auth-page.canvas {
        grid-template-columns: minmax(0, 26rem) minmax(0, 1fr);
        gap: var(--space-4);
        padding: var(--space-4);
        background: var(--color-bg-secondary);
    }
    .canvas .auth-panel {
        padding: clamp(var(--space-6), 4vw, var(--space-10));
        border-radius: var(--corner-lg);
        background: var(--color-surface-raised);
    }
    .canvas .auth-body {
        max-width: none;
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
        /* The video switch's notch covers this corner; a square corner underneath
           avoids a faint seam where two anti-aliased curves would overlap. The
           bottom-right corner does the same once the credit notch fades in. */
        border-top-right-radius: 0;
        background: color-mix(in oklab, var(--color-accent-100) 55%, var(--color-bg-secondary));
        transition: border-bottom-right-radius var(--duration-medium) var(--easing-out);
    }
    /* No switch, no notch: the corner is rounded like the others. */
    .auth-canvas:not(.has-switch) {
        border-top-right-radius: var(--corner-lg);
    }
    /* Only the credit's notch needs the square corner; without a credit it stays rounded. */
    .auth-canvas.has-video.has-credit {
        border-bottom-right-radius: 0;
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
    /* An uploaded logo fills the same width; its hidden image keeps the aspect ratio. */
    .auth-canvas :global(.auth-canvas-mark .mark.image) {
        display: block;
        max-width: none;
    }
    .auth-canvas :global(.auth-canvas-mark .mark.image img) {
        width: 100%;
        height: auto;
        max-width: none;
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
    /* Frame notches: the video switch (top right) and the credit (bottom right)
       sit in cut-outs of the canvas, so the page frame reaches into the picture
       and holds them. The pseudo-elements paint the two concave fillets that join
       each notch to the frame. */
    .auth-video-toggle,
    .auth-canvas :global(.auth-credit) {
        --notch-radius: var(--corner-md);
        position: absolute;
        right: 0;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3);
        background: var(--color-bg-secondary);
        color: var(--color-text);
        font-size: var(--font-size-xs);
    }
    .auth-video-toggle::before,
    .auth-video-toggle::after,
    .auth-canvas :global(.auth-credit::before),
    .auth-canvas :global(.auth-credit::after) {
        content: '';
        position: absolute;
        width: var(--notch-radius);
        height: var(--notch-radius);
        pointer-events: none;
    }
    /* Unlike the credit the switch stays visible without a video — it is the
       way back to one once it has been turned off. */
    .auth-video-toggle {
        top: 0;
        border-bottom-left-radius: var(--notch-radius);
        cursor: pointer;
        user-select: none;
    }
    .auth-video-toggle::before,
    .auth-video-toggle::after {
        background: radial-gradient(
            circle at 0 100%,
            transparent calc(var(--notch-radius) - 0.5px),
            var(--color-bg-secondary) calc(var(--notch-radius) + 0.5px)
        );
    }
    .auth-video-toggle::before {
        top: 0;
        right: 100%;
    }
    .auth-video-toggle::after {
        top: 100%;
        right: 0;
    }
    .auth-canvas :global(.auth-credit) {
        bottom: 0;
        border-top-left-radius: var(--notch-radius);
        text-decoration: none;
        opacity: 0;
        transition: opacity var(--duration-medium) var(--easing-out);
    }
    .auth-canvas :global(.auth-credit::before),
    .auth-canvas :global(.auth-credit::after) {
        background: radial-gradient(
            circle at 0 0,
            transparent calc(var(--notch-radius) - 0.5px),
            var(--color-bg-secondary) calc(var(--notch-radius) + 0.5px)
        );
    }
    .auth-canvas :global(.auth-credit::before) {
        bottom: 0;
        right: 100%;
    }
    .auth-canvas :global(.auth-credit::after) {
        bottom: 100%;
        right: 0;
    }
    .has-video :global(.auth-credit) {
        opacity: 1;
    }
    .auth-canvas :global(.auth-credit:hover),
    .auth-canvas :global(.auth-credit:focus-visible) {
        text-decoration: underline;
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
        .auth-canvas,
        .auth-canvas :global(.auth-credit) {
            transition: none;
        }
    }

    /* ── Small screens ────────────────────────────────────────────────── */
    @media (--bp-sm-and-smaller) {
        .auth-panel {
            gap: var(--space-6);
            padding: var(--space-4);
        }
        .auth-page.canvas {
            grid-template-columns: minmax(0, 1fr);
            padding: var(--space-3);
        }
        .canvas .auth-panel {
            padding: clamp(var(--space-6), 4vw, var(--space-10));
        }
        .auth-canvas {
            display: none;
        }
    }
</style>
