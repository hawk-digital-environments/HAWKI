import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import {hawkiConnection} from './util/hawkiConnection.js';

window.Pusher = Pusher;

const wsConfig = hawkiConnection('transfer.websocket');

if (wsConfig) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: wsConfig.key,
        wsHost: wsConfig.host,
        wsPath: wsConfig.path || undefined,
        wsPort: wsConfig.port,
        wssPort: wsConfig.port,
        forceTLS: wsConfig.forceTLS,
        enabledTransports: ['ws', 'wss']
    });
} else {
    console.warn('No frontend connection element found, Echo will not be initialized.');
}
