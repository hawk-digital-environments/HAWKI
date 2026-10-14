import type {ClientStorage} from '$lib/kernel/storage/StorageExtension.js';

/** One background of the sign-in page, as the public configuration lists it (`branding.loginBackgrounds`). */
export interface LoginBackground {
    url: string;
    kind: 'image' | 'video';
    /** Credit shown in the canvas corner, or null for none. */
    credit: string | null;
    /** Where the credit links to, or null when it is plain text. */
    link: string | null;
}

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
 * Picks the background for the login canvas.
 *
 * Every visit advances to the next one so returning users see them in turn; the position is
 * remembered in the browser. Null without backgrounds: the canvas then shows the logo.
 */
export function pickLoginBackground(backgrounds: readonly LoginBackground[], storage: ClientStorage): LoginBackground | null {
    if (backgrounds.length === 0) return null;
    return backgrounds[nextPosition(storage, backgrounds.length)];
}

function nextPosition(storage: ClientStorage, length: number): number {
    const key = STORAGE_KEY;
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
