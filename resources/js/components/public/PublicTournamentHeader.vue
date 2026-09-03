<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AtSign,
    CalendarDays,
    ChevronDown,
    GitFork,
    Globe2,
    MapPin,
    Phone,
    Radio,
    TableProperties,
} from '@lucide/vue';
import { computed } from 'vue';
import PublicTournamentLiveQrCode from '@/components/public/PublicTournamentLiveQrCode.vue';
import type {
    PublicTournamentHeaderData,
    PublicTournamentPage,
    PublicVenueBranding,
} from '@/types';

const props = defineProps<{
    venue: PublicVenueBranding;
    tournament: PublicTournamentHeaderData;
    activePage: PublicTournamentPage;
}>();

const pages = [
    {
        key: 'live',
        label: 'Uživo',
        description: 'Trenutni mečevi',
        icon: Radio,
    },
    {
        key: 'groups',
        label: 'Grupe',
        description: 'Tabela i plasman',
        icon: TableProperties,
    },
    {
        key: 'schedule',
        label: 'Raspored',
        description: 'Svi mečevi',
        icon: CalendarDays,
    },
    {
        key: 'knockout',
        label: 'Nokaut',
        description: 'Eliminacioni kostur',
        icon: GitFork,
    },
] as const;

const venueInitials = computed(() =>
    props.venue.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase(),
);

const instagramLabel = computed(() => {
    if (!props.venue.instagram_url) {
        return null;
    }

    try {
        const url = new URL(props.venue.instagram_url);
        const handle = url.pathname.split('/').filter(Boolean)[0];

        return handle ? `@${handle}` : 'Instagram';
    } catch {
        return 'Instagram';
    }
});

const websiteLabel = computed(() => {
    if (!props.venue.website_url) {
        return null;
    }

    try {
        return new URL(props.venue.website_url).hostname.replace(/^www\./, '');
    } catch {
        return 'Web sajt';
    }
});
</script>

<template>
    <header
        class="overflow-hidden rounded-2xl border border-[var(--public-border)] bg-[var(--public-panel)] shadow-sm md:rounded-3xl"
    >
        <div
            class="flex flex-col gap-3 p-3.5 md:flex-row md:items-center md:justify-between md:gap-5 md:p-7 2xl:gap-4 2xl:p-5"
        >
            <div
                class="flex min-w-0 items-start gap-3 md:flex-1 md:items-center md:gap-5"
            >
                <img
                    v-if="venue.logo_url"
                    :src="venue.logo_url"
                    :alt="`${venue.name} logo`"
                    class="size-14 shrink-0 rounded-xl border border-[var(--public-border)] bg-white object-contain p-1 shadow-sm md:size-20 md:rounded-2xl md:p-1.5"
                />
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-lg font-black text-white shadow-sm md:size-20 md:rounded-2xl md:text-2xl"
                    aria-hidden="true"
                >
                    {{ venueInitials }}
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[var(--public-muted)] uppercase"
                        >
                            {{ venue.name }}
                        </p>
                    </div>

                    <h1
                        class="mt-1 line-clamp-2 text-xl leading-[1.1] font-black tracking-tight md:line-clamp-none md:text-5xl"
                    >
                        {{ tournament.name }}
                    </h1>

                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-[var(--public-muted)] md:mt-3 md:gap-x-3 md:text-sm"
                    >
                        <span>{{ tournament.status_label }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ tournament.game_type }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ tournament.match_mode }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="
                    venue.address ||
                    venue.phone ||
                    venue.instagram_url ||
                    venue.website_url
                "
                class="hidden max-w-xl flex-wrap gap-2 md:flex md:max-w-sm md:justify-end"
            >
                <span
                    v-if="venue.address"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-3 py-1.5 text-xs text-[var(--public-muted)]"
                >
                    <MapPin class="size-3.5" />
                    {{ venue.address }}
                </span>
                <a
                    v-if="venue.phone"
                    :href="`tel:${venue.phone}`"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-3 py-1.5 text-xs text-[var(--public-muted)] transition hover:text-[var(--public-fg)]"
                >
                    <Phone class="size-3.5" />
                    {{ venue.phone }}
                </a>
                <a
                    v-if="venue.instagram_url"
                    :href="venue.instagram_url"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-3 py-1.5 text-xs text-[var(--public-muted)] transition hover:text-[var(--public-fg)]"
                >
                    <AtSign class="size-3.5" />
                    {{ instagramLabel }}
                </a>
                <a
                    v-if="venue.website_url"
                    :href="venue.website_url"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-3 py-1.5 text-xs text-[var(--public-muted)] transition hover:text-[var(--public-fg)]"
                >
                    <Globe2 class="size-3.5" />
                    {{ websiteLabel }}
                </a>
            </div>

            <PublicTournamentLiveQrCode :public-code="tournament.public_code" />

            <details
                v-if="venue.address || venue.instagram_url || venue.website_url"
                class="group -mx-0.5 md:hidden"
            >
                <summary
                    class="flex min-h-9 cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-[var(--public-border)] bg-[var(--public-nav)] px-3 py-2 text-xs font-medium text-[var(--public-muted)]"
                >
                    <span class="inline-flex min-w-0 items-center gap-2">
                        <MapPin class="size-3.5 shrink-0 text-orange-300" />
                        Info o lokalu
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 transition group-open:rotate-180"
                    />
                </summary>

                <div class="mt-2 flex flex-wrap gap-2 px-0.5">
                    <span
                        v-if="venue.address"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-2.5 py-1.5 text-[11px] text-[var(--public-muted)]"
                    >
                        <MapPin class="size-3.5" />
                        {{ venue.address }}
                    </span>
                    <a
                        v-if="venue.instagram_url"
                        :href="venue.instagram_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-2.5 py-1.5 text-[11px] text-[var(--public-muted)]"
                    >
                        <AtSign class="size-3.5" />
                        {{ instagramLabel }}
                    </a>
                    <a
                        v-if="venue.website_url"
                        :href="venue.website_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[var(--public-border)] bg-[var(--public-nav)] px-2.5 py-1.5 text-[11px] text-[var(--public-muted)]"
                    >
                        <Globe2 class="size-3.5" />
                        {{ websiteLabel }}
                    </a>
                </div>
            </details>
        </div>

        <nav
            aria-label="Javne stranice turnira"
            class="grid grid-cols-4 gap-1 border-t border-[var(--public-border)] bg-[var(--public-nav)] p-1"
        >
            <Link
                v-for="page in pages"
                :key="page.key"
                :href="`/t/${tournament.public_code}/${page.key}`"
                class="group flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-center transition md:flex-row md:gap-2 md:rounded-2xl md:px-2 md:py-3"
                :class="
                    activePage === page.key
                        ? 'bg-orange-500 text-white shadow-sm'
                        : 'text-[var(--public-muted)] hover:bg-[var(--public-panel)] hover:text-[var(--public-fg)]'
                "
                :aria-current="activePage === page.key ? 'page' : undefined"
            >
                <component
                    :is="page.icon"
                    class="size-[18px] shrink-0 md:size-5"
                />
                <span class="min-w-0">
                    <span
                        class="block truncate text-[11px] leading-none font-bold sm:text-xs md:text-sm md:leading-normal"
                    >
                        {{ page.label }}
                    </span>
                    <span
                        class="hidden truncate text-[10px] opacity-70 lg:block"
                    >
                        {{ page.description }}
                    </span>
                </span>
            </Link>
        </nav>
    </header>
</template>
