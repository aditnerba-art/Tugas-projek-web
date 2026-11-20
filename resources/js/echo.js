import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const driver = import.meta.env.VITE_REALTIME_DRIVER ?? 'reverb';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (driver === 'reverb') {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? 'local',
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.slice(0, -1)) === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': csrfToken } },
    });
}

function listen({ endpoint, channel, onUpdate }) {
    let etag = '';
    let timer = null;
    let subscribedChannel = null;

    const poll = async () => {
        const headers = etag ? { 'If-None-Match': etag } : {};

        try {
            const response = await fetch(endpoint, { headers });
            if (response.status === 304 || !response.ok) return;

            etag = response.headers.get('ETag') || etag;
            onUpdate(await response.json(), 'polling');
        } catch (error) {
            console.error('Realtime polling gagal', error);
        }
    };

    const startPolling = () => {
        if (timer !== null) return;
        poll();
        timer = window.setInterval(poll, 12000);
    };
    const stopPolling = () => {
        if (timer === null) return;
        window.clearInterval(timer);
        timer = null;
    };

    if (window.Echo && channel) {
        subscribedChannel = channel;
        channel.listen('.service.status.changed', event => onUpdate(event, 'reverb'));
        channel.subscribed(stopPolling);
        channel.error(startPolling);

        const connection = window.Echo.connector.pusher.connection;
        connection.bind('connected', stopPolling);
        connection.bind('disconnected', startPolling);
        connection.bind('unavailable', startPolling);
        connection.bind('failed', startPolling);
        if (connection.state !== 'connected') startPolling();
    } else {
        startPolling();
    }

    return () => {
        stopPolling();
        if (subscribedChannel) window.Echo.leave(subscribedChannel.name);
    };
}

window.ServiceRealtime = {
    startStaff(role, onUpdate) {
        return fetch('/api/auth/me')
            .then(response => response.ok ? response.json() : Promise.reject(new Error('Sesi staf tidak berlaku')))
            .then(({ user }) => {
                const channel = user.role === 'teknisi'
                    ? window.Echo?.private(`staff.technician.${user.id}`)
                    : window.Echo?.private('staff.services');

                return listen({ endpoint: '/api/services/status-updates', channel, onUpdate });
            })
            .catch(error => {
                console.error('Kanal realtime staf gagal disiapkan', error);
                return listen({ endpoint: '/api/services/status-updates', onUpdate });
            });
    },

    startCustomer(channelKey, onUpdate) {
        const channel = channelKey ? window.Echo?.private(`customer-status.${channelKey}`) : null;
        return listen({ endpoint: '/api/customer/status-updates', channel, onUpdate });
    },
};
