import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';

import type { TournamentPublicLink } from '@/types/tournament';

type UseTournamentPublicLinksOptions = {
    publicCode: () => string;
    publicEnabled: () => boolean;
};

export const useTournamentPublicLinks = ({
    publicCode,
    publicEnabled,
}: UseTournamentPublicLinksOptions) => {
    const copiedPublicLink = ref<string | null>(null);

    const publicBaseUrl = computed(() => {
        if (typeof window === 'undefined') {
            return '';
        }

        return `${window.location.origin}/t/${publicCode()}`;
    });

    const publicLiveUrl = computed(() => `${publicBaseUrl.value}/live`);

    const publicQrCodeDataUrl = ref<string | null>(null);

    const publicLinks = computed<TournamentPublicLink[]>(() => [
        {
            key: 'live',
            label: 'Live',
            description:
                'Glavna public strana sa trenutnim, sledećim i poslednjim mečevima.',
            url: publicLiveUrl.value,
        },
        {
            key: 'groups',
            label: 'Grupe',
            description:
                'Tabela grupa, prolaz, repasaž i grupni mečevi.',
            url: `${publicBaseUrl.value}/groups`,
        },
        {
            key: 'schedule',
            label: 'Raspored',
            description:
                'Kompletan public raspored svih mečeva.',
            url: `${publicBaseUrl.value}/schedule`,
        },
        {
            key: 'knockout',
            label: 'Nokaut',
            description:
                'Public prikaz nokaut serija, legova i pobednika.',
            url: `${publicBaseUrl.value}/knockout`,
        },
    ]);

    const generatePublicQrCode = async () => {
        if (
            !publicEnabled()
            || !publicLiveUrl.value
            || typeof window === 'undefined'
        ) {
            publicQrCodeDataUrl.value = null;

            return;
        }

        publicQrCodeDataUrl.value = await QRCode.toDataURL(
            publicLiveUrl.value,
            {
                width: 420,
                margin: 2,
                errorCorrectionLevel: 'M',
            },
        );
    };

    watch(
        [publicLiveUrl, () => publicEnabled()],
        () => {
            void generatePublicQrCode();
        },
        {
            immediate: true,
        },
    );

    const copyPublicLink = async (key: string, url: string) => {
        await navigator.clipboard.writeText(url);

        copiedPublicLink.value = key;

        window.setTimeout(() => {
            if (copiedPublicLink.value === key) {
                copiedPublicLink.value = null;
            }
        }, 1600);
    };

    return {
        copiedPublicLink,
        publicBaseUrl,
        publicLiveUrl,
        publicQrCodeDataUrl,
        publicLinks,
        copyPublicLink,
    };
};
