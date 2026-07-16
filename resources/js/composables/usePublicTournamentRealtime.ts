import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type RealtimeStatus = 'connecting' | 'connected' | 'updated' | 'error';

type UsePublicTournamentRealtimeOptions = {
    publicCode: string;
    only?: string[];
};

export function usePublicTournamentRealtime(options: UsePublicTournamentRealtimeOptions) {
    const realtimeStatus = ref<RealtimeStatus>('connecting');
    const lastRealtimeUpdateAt = ref<string | null>(null);

    const channelName = `public-tournament.${options.publicCode}`;

    const formatRealtimeTime = (): string => {
        return new Date().toLocaleTimeString('sr-RS', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    const reloadPublicTournamentData = () => {
        realtimeStatus.value = 'updated';
        lastRealtimeUpdateAt.value = formatRealtimeTime();

        const reloadOptions: {
            only?: string[];
            preserveScroll: boolean;
            preserveState: boolean;
        } = {
            preserveScroll: true,
            preserveState: true,
        };

        if (options.only?.length) {
            reloadOptions.only = options.only;
        }

        router.reload(reloadOptions);
    };

    onMounted(() => {
        const echo = (window as any).Echo;

        if (!echo) {
            realtimeStatus.value = 'error';

            return;
        }

        realtimeStatus.value = 'connected';

        echo
            .channel(channelName)
            .listen('.TournamentLiveUpdated', () => {
                reloadPublicTournamentData();
            });
    });

    onBeforeUnmount(() => {
        const echo = (window as any).Echo;

        if (!echo) {
            return;
        }

        echo.leave(channelName);
    });

    return {
        realtimeStatus,
        lastRealtimeUpdateAt,
    };
}
