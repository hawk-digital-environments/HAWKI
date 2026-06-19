// @ts-nocheck
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import {getConfig} from '$lib/data/config/config.js';

export async function initializeEcho() {
    const config = getConfig('hawki-core');
    if (!config.transfer?.websocket) {
        return;
    }

    const wsConfig = config.transfer.websocket;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: wsConfig.key,
        wsHost: wsConfig.host,
        wsPath: wsConfig.path || undefined,
        wsPort: wsConfig.port,
        wssPort: wsConfig.port,
        forceTLS: wsConfig.forceTls,
        enabledTransports: ['ws', 'wss']
    });
}

window.Pusher = Pusher;
