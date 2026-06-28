<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type TournamentSettings = {
    direct_qualifiers_per_group?: number | null;
    repechage_enabled?: boolean;
    repechage_qualifiers_count?: number | null;
    repechage_participants_count?: number | null;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    settings: TournamentSettings;
    groups_count: number;
    participants_count: number;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Prolaz iz grupe',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    direct_qualifiers_per_group: props.tournament.settings.direct_qualifiers_per_group ?? 2,
    repechage_enabled: props.tournament.settings.repechage_enabled ?? true,
    repechage_participants_count: props.tournament.settings.repechage_participants_count ?? null,
    repechage_qualifiers_count: props.tournament.settings.repechage_qualifiers_count ?? null,
});

const submit = () => {
    form.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/qualification/setup`);
};
</script>

<template>
    <Head :title="`Prolaz iz grupe - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Podešavanje prolaza iz grupe
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Ovde podešavaš koliko učesnika iz svake grupe ide direktno dalje i da li ostali idu u repasaž.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/standings`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <form
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-medium">
                        Pravila prolaza
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Ova podešavanja utiču na status u tabeli grupa: Direktan prolaz, Repasaž ili Ispao.
                    </p>
                </div>

                <div class="mt-5 space-y-5">
                    <div>
                        <label class="text-sm font-medium">
                            Direktno prolazi po grupi
                        </label>

                        <input
                            v-model="form.direct_qualifiers_per_group"
                            type="number"
                            min="0"
                            max="16"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                        >

                        <p class="mt-1 text-xs text-muted-foreground">
                            Primer: ako staviš 2, prva dva iz svake grupe imaju status Direktan prolaz.
                        </p>

                        <p
                            v-if="form.errors.direct_qualifiers_per_group"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.direct_qualifiers_per_group }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <label class="flex items-start gap-3">
                            <input
                                v-model="form.repechage_enabled"
                                type="checkbox"
                                class="mt-1"
                            >

                            <span>
                                <span class="block text-sm font-medium">
                                    Repasaž uključen
                                </span>

                                <span class="mt-1 block text-xs text-muted-foreground">
                                    Ako je uključeno, učesnici koji nisu direktno prošli biće označeni kao Repasaž.
                                    Ako nije uključeno, biće označeni kao Ispao.
                                </span>
                            </span>
                        </label>

                        <p
                            v-if="form.errors.repechage_enabled"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.repechage_enabled }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Ukupno učesnika ide u repasaž
                        </label>

                        <input
                            v-model="form.repechage_participants_count"
                            type="number"
                            min="0"
                            max="128"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Primer: 8"
                        >

                        <p class="mt-1 text-xs text-muted-foreground">
                            Ovaj broj se deli ravnomerno po grupama. Primer: 8 grupa i ukupno 8 u repasažu znači da iz svake grupe još 1 učesnik ide u repasaž.
                        </p>

                        <p
                            v-if="form.errors.repechage_participants_count"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.repechage_participants_count }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Broj učesnika koji prolazi iz repasaža
                        </label>

                        <input
                            v-model="form.repechage_qualifiers_count"
                            type="number"
                            min="0"
                            max="64"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Opciono"
                        >

                        <p class="mt-1 text-xs text-muted-foreground">
                            Ovo još ne koristimo za generisanje repasaža, ali ga čuvamo za sledeći korak.
                        </p>

                        <p
                            v-if="form.errors.repechage_qualifiers_count"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.repechage_qualifiers_count }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Sačuvaj podešavanja
                    </button>

                    <Link
                        :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/standings`"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    >
                        Otkaži
                    </Link>
                </div>
            </form>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-lg font-medium">
                    Pregled
                </h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Grupe</span>
                        <span class="font-medium">{{ tournament.groups_count }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Učesnici</span>
                        <span class="font-medium">{{ tournament.participants_count }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Direktno po grupi</span>
                        <span class="font-medium">{{ form.direct_qualifiers_per_group }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Repasaž</span>
                        <span class="font-medium">{{ form.repechage_enabled ? 'Da' : 'Ne' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Ukupno u repasažu</span>
                        <span class="font-medium">{{ form.repechage_participants_count ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Repasaž po grupi</span>
                        <span class="font-medium">
                            <template
                                v-if="
                                    form.repechage_enabled
                                        && form.repechage_participants_count
                                        && tournament.groups_count > 0
                                        && Number(form.repechage_participants_count) % tournament.groups_count === 0
                                "
                            >
                                {{ Number(form.repechage_participants_count) / tournament.groups_count }}
                            </template>

                            <template v-else>
                                -
                            </template>
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Iz repasaža dalje</span>
                        <span class="font-medium">{{ form.repechage_qualifiers_count ?? '-' }}</span>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border">
                    Primer za grupu od 4 učesnika i 2 direktna prolaza:
                    prva 2 imaju status Direktan prolaz, ostali Repasaž ili Ispao.
                </div>
            </div>
        </div>
    </div>
</template>
