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
