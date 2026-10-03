import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

// Derive from current location for LAN/shared access (works for .test and LAN IP/ports)
const reverbScheme = window.location.protocol === 'https:' ? 'https' : 'http';
const reverbHost = window.location.hostname;
const reverbPort = window.location.port ? Number(window.location.port) : (reverbScheme === 'https' ? 443 : 80);

if (reverbKey) {
    Pusher.logToConsole = true;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbScheme === 'http' ? reverbPort : (reverbPort === 443 ? 8080 : reverbPort),
        wssPort: reverbScheme === 'https' ? reverbPort : null,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        enableStats: false,
        auth: {
            headers: {
                'Accept': 'application/json',
            },
        },
    });
    console.log('Echo initialized');
} else {
    console.warn('Echo skipped: VITE_REVERB_APP_KEY is not set');
}