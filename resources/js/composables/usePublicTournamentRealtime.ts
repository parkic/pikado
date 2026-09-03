import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

type PusherConnection = {
    bind: (event: string, callback: () => void) => void;
    unbind: (event: string, callback: () => void) => void;
    state?: string;
};

type EchoClient = {
    channel: (name: string) => {
        listen: (event: string, callback: () => void) => void;
    };
    leave: (name: string) => void;
    connector?: {
        pusher?: {
            connection?: PusherConnection;
        };
    };
};

const FALLBACK_POLLING_INTERVAL_MS = 2_000;
const SOCKET_BACKUP_POLLING_INTERVAL_MS = 15_000;

type UsePublicTournamentRealtimeOptions = {
    publicCode: string;
    only?: string[];
};

export function usePublicTournamentRealtime(
    options: UsePublicTournamentRealtimeOptions,
) {
    let pollingTimer: number | null = null;
    let socketConnected = false;
    let reloadInProgress = false;
    let queuedReloadSource: 'socket' | 'polling' | null = null;
    let lastPollingReloadAt = 0;

    const channelName = `public-tournament.${options.publicCode}`;

    const reloadPublicTournamentData = (source: 'socket' | 'polling') => {
        if (reloadInProgress) {
            queuedReloadSource = source;

            return;
        }

        reloadInProgress = true;

        const reloadOptions: {
            only?: string[];
            preserveScroll: boolean;
            preserveState: boolean;
            onFinish: () => void;
        } = {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                reloadInProgress = false;

                if (queuedReloadSource) {
                    const nextSource = queuedReloadSource;
                    queuedReloadSource = null;
                    window.setTimeout(
                        () => reloadPublicTournamentData(nextSource),
                        0,
                    );
                }
            },
        };

        if (options.only?.length) {
            reloadOptions.only = options.only;
        }

        router.reload(reloadOptions);
    };

    const refreshFromPolling = () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        const now = Date.now();
        const pollingInterval = socketConnected
            ? SOCKET_BACKUP_POLLING_INTERVAL_MS
            : FALLBACK_POLLING_INTERVAL_MS;

        if (now - lastPollingReloadAt < pollingInterval) {
            return;
        }

        lastPollingReloadAt = now;
        reloadPublicTournamentData('polling');
    };

    const handleConnected = () => {
        socketConnected = true;
    };

    const handleDisconnected = () => {
        socketConnected = false;
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            refreshFromPolling();
        }
    };

    onMounted(() => {
        const echo = window.Echo as EchoClient | undefined;

        pollingTimer = window.setInterval(
            refreshFromPolling,
            FALLBACK_POLLING_INTERVAL_MS,
        );
        document.addEventListener('visibilitychange', handleVisibilityChange);

        if (!echo) {
            return;
        }

        const connection = echo.connector?.pusher?.connection;

        if (connection) {
            connection.bind('connected', handleConnected);
            connection.bind('disconnected', handleDisconnected);
            connection.bind('unavailable', handleDisconnected);
            connection.bind('failed', handleDisconnected);

            if (connection.state === 'connected') {
                handleConnected();
            }
        }

        echo.channel(channelName).listen('.TournamentLiveUpdated', () => {
            socketConnected = true;
            reloadPublicTournamentData('socket');
        });
    });

    onBeforeUnmount(() => {
        const echo = window.Echo as EchoClient | undefined;
        const connection = echo?.connector?.pusher?.connection;

        if (pollingTimer) {
            window.clearInterval(pollingTimer);
        }

        document.removeEventListener(
            'visibilitychange',
            handleVisibilityChange,
        );

        connection?.unbind('connected', handleConnected);
        connection?.unbind('disconnected', handleDisconnected);
        connection?.unbind('unavailable', handleDisconnected);
        connection?.unbind('failed', handleDisconnected);

        if (!echo) {
            return;
        }

        echo.leave(channelName);
    });
}
