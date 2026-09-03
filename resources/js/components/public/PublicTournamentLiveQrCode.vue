<script setup lang="ts">
import QRCode from 'qrcode';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    publicCode: string;
}>();

const liveUrl = ref('');
const qrCodeDataUrl = ref<string | null>(null);

const generateQrCode = async (): Promise<void> => {
    if (typeof window === 'undefined') {
        return;
    }

    liveUrl.value = new URL(
        `/t/${encodeURIComponent(props.publicCode)}/live`,
        window.location.origin,
    ).toString();

    try {
        qrCodeDataUrl.value = await QRCode.toDataURL(liveUrl.value, {
            width: 480,
            margin: 2,
            errorCorrectionLevel: 'M',
            color: {
                dark: '#09090B',
                light: '#FFFFFF',
            },
        });
    } catch {
        qrCodeDataUrl.value = null;
    }
};

onMounted(() => {
    void generateQrCode();
});

watch(
    () => props.publicCode,
    () => {
        void generateQrCode();
    },
);
</script>

<template>
    <aside
        v-if="qrCodeDataUrl"
        class="hidden shrink-0 2xl:block"
        aria-label="QR kod za praćenje turnira na telefonu"
        :data-live-url="liveUrl || undefined"
    >
        <img
            :src="qrCodeDataUrl"
            alt="QR kod koji otvara javni Live prikaz ovog turnira"
            class="size-40 rounded-xl bg-white p-1.5 shadow-sm"
        />
    </aside>
</template>
