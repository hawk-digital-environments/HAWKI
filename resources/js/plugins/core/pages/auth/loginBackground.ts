import type {AppTheme} from '$plugins/core/stores/ThemeStore.svelte.js';

/** One entry of the deployment's `public/bg_videos/bg_videos.json` index. */
export interface LoginBackgroundVideo {
    /** Absolute URL of the video file. */
    src: string;
    /** Display name of the video's creator, shown as the credit. */
    creator: string;
    /** Where the credit links to. */
    link: string;
}

interface IndexEntry {
    creator: string;
    file: string;
    link: string;
}

interface VideoIndex {
    lightmode?: IndexEntry[];
    darkmode?: IndexEntry[];
}

const STORAGE_KEY = 'hawki.auth.background';

/**
 * Picks the background video for the login canvas.
 *
 * Deployments drop their videos into `public/bg_videos/` and list them in
 * `bg_videos.json`, one list per theme. Every visit advances to the next entry so
 * returning users see the videos in turn; the position is remembered per theme.
 * Resolves to `null` when the index is missing, empty, or malformed — the canvas
 * then falls back to the wordmark.
 */
export async function pickLoginBackground(baseUrl: string, theme: AppTheme): Promise<LoginBackgroundVideo | null> {
    const root = `${baseUrl.replace(/\/+$/, '')}/bg_videos`;
    let index: VideoIndex;
    try {
        const response = await fetch(`${root}/bg_videos.json`, {credentials: 'omit'});
        if (!response.ok) return null;
        index = (await response.json()) as VideoIndex;
    } catch {
        return null;
    }
    const entries = (theme === 'dark' ? index.darkmode : index.lightmode) ?? [];
    const valid = entries.filter(
        (entry): entry is IndexEntry =>
            typeof entry?.file === 'string' && typeof entry.creator === 'string' && typeof entry.link === 'string'
    );
    if (valid.length === 0) return null;

    const chosen = valid[nextPosition(theme, valid.length)];
    return {src: `${root}/${chosen.file}`, creator: chosen.creator, link: chosen.link};
}

function nextPosition(theme: AppTheme, length: number): number {
    const key = `${STORAGE_KEY}.${theme}`;
    let position = Math.floor(Math.random() * length);
    try {
        const previous = window.localStorage.getItem(key);
        if (previous !== null && Number.isInteger(Number(previous))) {
            position = (Number(previous) + 1) % length;
        }
        window.localStorage.setItem(key, String(position));
    } catch {
        // Storage may be unavailable (private mode, quota); a random pick is fine.
    }
    return position;
}
