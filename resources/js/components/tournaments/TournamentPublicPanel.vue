<script setup lang="ts">
import {
    ChevronDown,
    ExternalLink,
    Link as LinkIcon,
    QrCode,
} from '@lucide/vue';
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
    <section
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-medium">Javni prikaz</h2>
                    <span
                        class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            publicEnabled
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ publicEnabled ? 'Uključen' : 'Isključen' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Link za TV, igrače i publiku — radi bez prijave.
                </p>
            </div>

            <div v-if="publicEnabled" class="flex flex-col gap-2 sm:flex-row">
                <a
                    :href="publicLiveUrl"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                >
                    <ExternalLink class="size-4" />
                    Otvori uživo
                </a>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-sidebar-border/70 px-4 py-2.5 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    @click="emit('copyLink', 'live-main', publicLiveUrl)"
                >
                    <LinkIcon class="size-4" />
                    {{
                        copiedPublicLink === 'live-main'
                            ? 'Kopirano'
                            : 'Kopiraj link'
                    }}
                </button>
            </div>
        </div>

        <details
            v-if="publicEnabled"
            class="group mt-4 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-4"
            >
                <span class="flex items-center gap-2 text-sm font-medium">
                    <QrCode class="size-4 text-primary" />
                    QR kod i posebni prikazi
                </span>
                <ChevronDown
                    class="size-4 text-muted-foreground transition group-open:rotate-180"
                />
            </summary>

            <div class="mt-4 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-3 dark:border-sidebar-border"
                >
                    <img
                        v-if="publicQrCodeDataUrl"
                        :src="publicQrCodeDataUrl"
                        alt="QR kod za javni prikaz uživo"
                        class="h-auto w-full"
                    />
                    <div
                        v-else
                        class="flex aspect-square items-center justify-center text-sm text-muted-foreground"
                    >
                        QR se generiše...
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="publicLink in publicLinks"
                        :key="publicLink.key"
                        class="flex flex-col rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <p class="font-medium">{{ publicLink.label }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ publicLink.description }}
                        </p>
                        <div class="mt-auto flex gap-2 pt-4">
                            <a
                                :href="publicLink.url"
                                target="_blank"
                                class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground"
                            >
                                Otvori
                            </a>
                            <button
                                type="button"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium dark:border-sidebar-border"
                                @click="
                                    emit(
                                        'copyLink',
                                        publicLink.key,
                                        publicLink.url,
                                    )
                                "
                            >
                                {{
                                    copiedPublicLink === publicLink.key
                                        ? 'Kopirano'
                                        : 'Kopiraj'
                                }}
                            </button>
                        </div>
                    </article>
                </div>
            </div>

            <a
                v-if="publicQrCodeDataUrl"
                :href="publicQrCodeDataUrl"
                :download="`${tournamentSlug}-javni-prikaz-qr.png`"
                class="mt-3 inline-flex items-center text-sm font-medium text-primary hover:underline"
            >
                Preuzmi QR kod
            </a>
        </details>
    </section>
</template>
