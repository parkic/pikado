<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type TournamentGroup = {
    id: number;
    name: string;
    sort_order: number;
};

type TournamentSettings = {
    group_count?: number | null;
    group_size?: number | null;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    status: string;
    status_label: string;
    settings: TournamentSettings;
    participants_count: number;
    groups: TournamentGroup[];
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Setup grupa',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    group_count: props.tournament.settings.group_count ?? 4,
    group_size: props.tournament.settings.group_size ?? 4,
});

const submit = () => {
    form.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/groups/setup`);
};
</script>

<template>
    <Head :title="`Setup grupa - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Setup grupa
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Podesi broj grupa i broj mesta po grupi za turnir: {{ tournament.name }}.
                </p>
            </div>

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na turnir
            </Link>
        </div>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-medium">
                        Grupe
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Za sada pravimo samo prazne grupe. Učesnike ćemo dodavati u sledećem koraku.
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium">
                                Broj grupa
                            </label>

                            <input
                                v-model.number="form.group_count"
                                type="number"
                                min="1"
                                max="32"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >

                            <p
                                v-if="form.errors.group_count"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.group_count }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium">
                                Broj mesta po grupi
                            </label>

                            <input
                                v-model.number="form.group_size"
                                type="number"
                                min="2"
                                max="16"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >

                            <p
                                v-if="form.errors.group_size"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.group_size }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="tournament.participants_count > 0"
                        class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-700 dark:text-yellow-300"
                    >
                        Ovaj turnir već ima učesnike. Promena grupa je zaključana da ne bismo pokvarili raspored.
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-medium">
                        Trenutne grupe
                    </h2>

                    <div
                        v-if="tournament.groups.length"
                        class="mt-4 grid gap-3 md:grid-cols-4"
                    >
                        <div
                            v-for="group in tournament.groups"
                            :key="group.id"
                            class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                        >
                            <p class="text-sm text-muted-foreground">
                                Grupa
                            </p>

                            <p class="mt-1 text-lg font-medium">
                                {{ group.name }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
                    >
                        Grupe još nisu napravljene.
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
                <h2 class="text-lg font-medium">
                    Review
                </h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Turnir</span>
                        <span class="font-medium text-right">{{ tournament.name }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Status</span>
                        <span class="font-medium">{{ tournament.status_label }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Broj grupa</span>
                        <span class="font-medium">{{ form.group_count }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Mesta po grupi</span>
                        <span class="font-medium">{{ form.group_size }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Ukupno mesta</span>
                        <span class="font-medium">{{ form.group_count * form.group_size }}</span>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || tournament.participants_count > 0"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Čuvam...' : 'Sačuvaj grupe' }}
                </button>
            </div>
        </form>
    </div>
</template>
