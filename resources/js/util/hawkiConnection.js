let parsedConnectionData = null;

/**
 * Provides access to the frontend connection data, which includes websocket configuration and other connection-related
 * information. This is a temporary workaround for accessing this data until the new frontend stack is fully implemented
 * and provides a more robust solution.
 * @param keyOrPath
 * @return {*}
 */
export function hawkiConnection(keyOrPath) {
    if (!parsedConnectionData) {
        const frontendConnectionEl = document.getElementById('frontend-connection');
        if (frontendConnectionEl) {
            parsedConnectionData = JSON.parse(frontendConnectionEl.textContent);
        } else {
            console.warn('No frontend connection element found, hawkiConnection will not be able to provide connection data.');
            return null;
        }
    }

    if (keyOrPath === undefined || keyOrPath === null) {
        return parsedConnectionData;
    }

    const pathParts = (typeof keyOrPath === 'string' ? keyOrPath : '').split('.');
    let current = parsedConnectionData;

    if (current === null || pathParts.length === 0) {
        return null;
    }

    for (const part of pathParts) {
        if (current[part] === undefined) {
            console.warn(`Connection data does not contain key: ${keyOrPath}`);
            return null;
        }
        current = current[part];
    }

    return current;
}
