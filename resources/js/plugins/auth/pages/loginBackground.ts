import z from 'zod';
import type {ClientStorage} from '$lib/kernel/storage/StorageExtension.js';

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

const IndexEntrySchema = z.object({creator: z.string(), file: z.string().min(1), link: z.url().refine(value => ['https:', 'http:'].includes(new URL(value).protocol))});
const VideoIndexSchema = z.object({lightmode: z.array(IndexEntrySchema).optional(), darkmode: z.array(IndexEntrySchema).optional()});

const STORAGE_KEY = 'hawki.auth.background';
const VIDEO_PREFERENCE_KEY = 'hawki.auth.backgroundVideo';

/**
 * Whether the login canvas may load the background video.
 *
 * Playing a video costs energy, so low-power devices can switch it off; the
 * choice is remembered across visits. Enabled unless the stored value is `'0'`,
 * and enabled when the storage cannot be read.
 */
export function isLoginVideoEnabled(storage: ClientStorage): boolean {
    try {
        return storage.getItem(VIDEO_PREFERENCE_KEY) !== '0';
    } catch {
        // Storage may be unavailable (private mode, quota); showing the video is fine.
        return true;
    }
}

/** Remembers the choice: stores `'0'` when disabled, drops the key when enabled. */
export function setLoginVideoEnabled(storage: ClientStorage, enabled: boolean): void {
    try {
        if (enabled) {
            storage.removeItem(VIDEO_PREFERENCE_KEY);
        } else {
            storage.setItem(VIDEO_PREFERENCE_KEY, '0');
        }
    } catch {
        // Storage may be unavailable (private mode, quota); the choice then lasts for this visit only.
    }
}

/**
 * Picks the background video for the login canvas.
 *
 * Deployments drop their videos into `public/bg_videos/` and list them in
 * `bg_videos.json`, one list per theme. Every visit advances to the next entry so
 * returning users see the videos in turn; the position is remembered per theme.
 * Resolves to `null` when the index is missing, empty, or malformed — the canvas
 * then falls back to the wordmark.
 */
export async function pickLoginBackground(baseUrl: string, theme: AppTheme, dependencies: {load: (url: string) => Promise<unknown>; storage: ClientStorage}): Promise<LoginBackgroundVideo | null> {
    const root = `${baseUrl.replace(/\/+$/, '')}/bg_videos`;
    let index: z.infer<typeof VideoIndexSchema>;
    try {
        index = VideoIndexSchema.parse(await dependencies.load(`${root}/bg_videos.json`));
    } catch {
        return null;
    }
    const valid = (theme === 'dark' ? index.darkmode : index.lightmode) ?? [];
    if (valid.length === 0) return null;

    const chosen = valid[nextPosition(dependencies.storage, theme, valid.length)];
    return {src: `${root}/${chosen.file}`, creator: chosen.creator, link: chosen.link};
}

function nextPosition(storage: ClientStorage, theme: AppTheme, length: number): number {
    const key = `${STORAGE_KEY}.${theme}`;
    let position = Math.floor(Math.random() * length);
    try {
        const previous = storage.getItem(key);
        if (previous !== null && Number.isSafeInteger(Number(previous)) && Number(previous) >= 0) {
            position = (Number(previous) + 1) % length;
        }
        storage.setItem(key, String(position));
    } catch {
        // Storage may be unavailable (private mode, quota); a random pick is fine.
    }
    return position;
}
