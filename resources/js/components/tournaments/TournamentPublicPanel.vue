<script setup lang="ts">
import type { TournamentPublicLink } from '@/types/tournament';

defineProps<{
    publicEnabled: boolean;
    publicQrCodeDataUrl: string | null;
    publicLiveUrl: string;
    publicLinks: TournamentPublicLink[];
    copiedPublicLink: string | null;
    tournamentSlug: string;
}>();

const emit = defineEmits<{
    copyLink: [key: string, url: string];
}>();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-medium">
                        Public prikaz
                    </h2>

                    <span
                        v-if="publicEnabled"
                        class="inline-flex w-fit rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300"
                    >
                        Public uključen
                    </span>

                    <span
                        v-else
                        class="inline-flex w-fit rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Public isključen
                    </span>
                </div>

                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Linkovi koje možeš da pošalješ igračima ili otvoriš na TV-u. Ove strane rade bez login-a.
                </p>
            </div>

            <div
                v-if="publicEnabled"
                class="flex flex-col gap-2 sm:flex-row"
            >
                <a
                    :href="publicLiveUrl"
                    target="_blank"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
                >
                    Otvori Live
                </a>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    @click="emit('copyLink', 'live-main', publicLiveUrl)"
                >
                    {{ copiedPublicLink === 'live-main' ? 'Kopirano' : 'Kopiraj Live link' }}
                </button>
            </div>
        </div>

        <div
            v-if="publicEnabled"
            class="mt-4 grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]"
        >
            <div class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                <div
                    v-if="publicQrCodeDataUrl"
                    class="rounded-lg bg-white p-3"
                >
                    <img
                        :src="publicQrCodeDataUrl"
                        alt="QR kod za public live prikaz"
                        class="h-auto w-full"
                    >
                </div>

                <div
                    v-else
                    class="flex aspect-square items-center justify-center rounded-lg border border-dashed border-sidebar-border/70 text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    QR se generiše...
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="font-medium">
                    QR kod za Live prikaz
                </p>

                <p class="mt-1 text-sm text-muted-foreground">
                    Ovaj QR vodi direktno na public live stranu turnira. Možeš ga otvoriti na TV-u, odštampati ili poslati igračima.
                </p>

                <p class="mt-3 break-all rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                    {{ publicLiveUrl }}
                </p>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <a
                        :href="publicLiveUrl"
                        target="_blank"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Otvori Live
                    </a>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        @click="emit('copyLink', 'qr-live', publicLiveUrl)"
                    >
                        {{ copiedPublicLink === 'qr-live' ? 'Kopirano' : 'Kopiraj link' }}
                    </button>

                    <a
                        v-if="publicQrCodeDataUrl"
                        :href="publicQrCodeDataUrl"
                        :download="`${tournamentSlug}-public-live-qr.png`"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    >
                        Preuzmi QR
                    </a>
                </div>
            </div>
        </div>

        <div
            v-if="publicEnabled"
            class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"
        >
            <div
                v-for="publicLink in publicLinks"
                :key="publicLink.key"
                class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex h-full flex-col gap-3">
                    <div>
                        <p class="font-medium">
                            {{ publicLink.label }}
                        </p>

                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ publicLink.description }}
                        </p>

                        <p class="mt-3 break-all rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                            {{ publicLink.url }}
                        </p>
                    </div>

                    <div class="mt-auto flex flex-col gap-2 sm:flex-row">
                        <a
                            :href="publicLink.url"
                            target="_blank"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground transition hover:opacity-90"
                        >
                            Otvori
                        </a>

                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                            @click="emit('copyLink', publicLink.key, publicLink.url)"
                        >
                            {{ copiedPublicLink === publicLink.key ? 'Kopirano' : 'Kopiraj' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Public prikaz je trenutno isključen za ovaj turnir.
        </div>
    </div>
</template>
