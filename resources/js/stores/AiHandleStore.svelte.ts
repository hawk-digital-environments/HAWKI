import {getConfig} from '$lib/data/config/config.js';

/**
 * Store for recognized `@handle` mentions in chat messages.
 *
 * Currently only recognizes the single configured HAWKI handle (e.g. `@hawki`),
 * but is structured to support additional handles (future assistant personas)
 * once the assistant system is implemented — `getHandlesIn` will be the place
 * where additional lookups are added.
 *
 * Use the exported `aiHandleStore` singleton.
 */
export class AiHandleStore {
    public constructor(
        /** The configured HAWKI `@handle` string (e.g. `'@hawki'`). */
        public readonly hawkiHandle: string
    ) {
    }

    /**
     * Generator that yields every recognized `@handle` found in `message`.
     *
     * Handles must start with `@`, consist of letters/digits/underscores/hyphens,
     * and be delimited by whitespace or appear at the start/end of the string.
     * Currently only yields matches against `hawkiHandle`.
     */
    public* getHandlesIn(message: string) {
        // Handles are a string starting with an @, followed by the handle name which can contain letters, numbers,
        // underscores, and hyphens. They must be separated from other words by spaces or be at the start/end
        // of the message.
        const genericHandleRegex = /(^|\s)(@[a-zA-Z0-9_-]+)(?=\s|$)/g;
        let match;

        // Currently we only match against the single AI handle, but as soon as the assistant systems are implemented
        // we will have various handles to work with, so this will be the place where we look them up in our store.
        while ((match = genericHandleRegex.exec(message + '')) !== null) {
            const handleName = match[2];
            if (handleName === this.hawkiHandle) {
                yield handleName;
            }
        }
    }
}

export const aiHandleStore = new AiHandleStore(getConfig().ai?.handle ?? '@hawki');
