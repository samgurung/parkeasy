import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const reverbPort = import.meta.env.VITE_REVERB_PORT ?? (reverbScheme === 'https' ? 443 : 80);

if (reverbKey) {
    Pusher.logToConsole = true;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: reverbPort,
        wssPort: 443,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws'],
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