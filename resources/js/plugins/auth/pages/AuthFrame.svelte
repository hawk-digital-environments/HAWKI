<!--
  @component Shell for the authentication pages (login, keychain setup, unlock, recovery).

  Two layouts. With `canvas` (the login page) the page splits into a narrow form
  panel on the left and a canvas on the right that shows the background an
  administrator configured (`branding.loginBackgrounds`, by default the videos in
  `public/bg_videos`), one per visit in turn; an image or a video. Behind it, or
  without one, the canvas shows an
  oversized, cropped wordmark, or the deployment's own logo in its place. The
  canvas collapses on small screens, where nothing is loaded.

  Without `canvas` (every page after the login) the content sits in a single
  centered column on the plain page background: wordmark top-left, content in the
  middle of the viewport, preferences bottom-left and the signed-in account with
  logout bottom-right.

  On the canvas, a small card in the lower left lists the newest entries of the
  news an administrator published (`branding.loginNews`), on top of the
  background.

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
    import Link from '$lib/components/util/link/Link.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useConfig } from '$lib/app/hooks/useConfig.svelte.js';
    import { useLegalLinks } from '$lib/app/hooks/useLegalLinks.svelte.js';
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import { useReducedMotion } from '$lib/utils/transitions/reducedMotion.svelte.js';
    import {
        pickLoginBackground,
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
    // Advances the rotation, so it runs once per visible canvas, not on every render.
    let picked = $state<LoginBackground | null>(null);
    $effect(() => {
        const backgrounds = branding?.loginBackgrounds ?? [];
        picked = canvasVisible ? pickLoginBackground(backgrounds, app.localStorage) : null;
    });
    const background = $derived(picked);
    /** At most three entries: the card is a glance at what is new, not an archive. */
    const news = $derived((branding?.loginNews ?? []).slice(0, 3));
    // Follows the language switch on this page.
    const newsDate = $derived(new Intl.DateTimeFormat(app.localization.locale.lang.replace('_', '-'), {dateStyle: 'long'}));
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
        <div class="auth-canvas" class:has-video={backgroundReady} class:has-credit={!!background?.credit}>
            <BrandLogo class="auth-canvas-mark" aria-hidden="true"/>
            {#if news.length}
                <section class="auth-news" aria-labelledby="auth-news-title">
                    <h2 id="auth-news-title">{__('ui.auth.news.title')}</h2>
                    <ol>
                        {#each news as item, index (item.date + item.title)}
                            <li style:--i={index}>
                                <time datetime={item.date}>{newsDate.format(new Date(`${item.date}T12:00:00`))}</time>
                                <h3>{item.title}</h3>
                                {#if item.text}<p>{item.text}</p>{/if}
                            </li>
                        {/each}
                    </ol>
                </section>
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
        background: color-mix(in oklab, var(--color-accent-100) 55%, var(--color-bg-secondary));
        transition: border-bottom-right-radius var(--duration-medium) var(--easing-out);
    }
    /* The credit's notch covers this corner; a square corner underneath avoids a faint
       seam where two anti-aliased curves would overlap. Without a credit it stays rounded. */
    .auth-canvas.has-video.has-credit {
        border-bottom-right-radius: 0;
    }
    :global(html.darkMode) .auth-canvas {
        background: color-mix(in oklab, var(--color-accent-fill) 45%, var(--color-bg-secondary));
    }
    /* News: a quiet, solid card in the lower left, legible over any background and
       above the wordmark and the video. */
    .auth-news {
        /* Inset from the canvas corner: as wide as the canvas's corner radius. */
        --news-inset: var(--space-6);
        position: absolute;
        left: var(--news-inset);
        bottom: var(--news-inset);
        z-index: 1;
        box-sizing: border-box;
        width: min(22rem, calc(100% - 2 * var(--space-6)));
        max-height: calc(100% - 2 * var(--space-6));
        overflow-y: auto;
        padding: var(--space-4) var(--space-5);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        color: var(--color-text);
        /* A slight overshoot: the card settles into place rather than stopping dead. */
        --easing-overshoot: cubic-bezier(0.34, 1.4, 0.64, 1);
        transform-origin: bottom left;
        animation: auth-news-in var(--duration-medium) var(--easing-overshoot) 150ms both;
    }
    .auth-news h2 {
        margin: 0 0 var(--space-2);
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
    }
    .auth-news ol {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .auth-news li {
        display: grid;
        gap: var(--space-0_5);
        padding-block: var(--space-3);
        animation: auth-news-item-in var(--duration-medium) var(--easing-overshoot) both;
        animation-delay: calc(250ms + var(--i) * 70ms);
    }
    .auth-news li + li {
        border-top: var(--border);
    }
    .auth-news li:last-child {
        padding-bottom: 0;
    }
    .auth-news time {
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
    }
    .auth-news h3 {
        margin: 0;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
    }
    .auth-news p {
        margin: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
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
    .has-video :global(.auth-canvas-mark) {
        visibility: hidden;
    }
    /* Frame notch: the credit (bottom right) sits in a cut-out of the canvas, so the
       page frame reaches into the picture and holds it. The pseudo-elements paint the
       two concave fillets that join the notch to the frame. */
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
    .auth-canvas :global(.auth-credit::before),
    .auth-canvas :global(.auth-credit::after) {
        content: '';
        position: absolute;
        width: var(--notch-radius);
        height: var(--notch-radius);
        pointer-events: none;
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
    @keyframes auth-news-in {
        from { opacity: 0; transform: translateY(16px) scale(0.96); }
        to { opacity: 1; transform: none; }
    }
    @keyframes auth-news-item-in {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: none; }
    }
    @keyframes auth-rise {
        from { opacity: 0; transform: translateY(34%); }
        to { opacity: 1; transform: translateY(26%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .auth-body,
        .auth-canvas :global(.auth-canvas-mark),
        .auth-news,
        .auth-news li {
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
