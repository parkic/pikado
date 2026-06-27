<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    match_mode: string;
    match_mode_label: string;
};

type Participant = {
    id: number;
    participant_type: string;
    group_position: string | null;
    display_name: string;
    player: {
        id: number;
        first_name: string;
        last_name: string;
        nickname: string | null;
    } | null;
    team: {
        id: number;
        name: string;
    } | null;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    participant: Participant;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Izmena učesnika',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    first_name: props.participant.player?.first_name ?? '',
    last_name: props.participant.player?.last_name ?? '',
    nickname: props.participant.player?.nickname ?? '',
    team_name: props.participant.team?.name ?? '',
});

const submit = () => {
    form.put(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/group-draw/participants/${props.participant.id}`,
    );
};
</script>

<template>
    <Head :title="`Izmena učesnika - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Izmena učesnika
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Menjamo osnovne podatke za učesnika u slotu {{ participant.group_position }}.
                </p>
            </div>

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/group-draw`"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na Group Draw
            </Link>
        </div>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-lg font-medium">
                    Podaci učesnika
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Promena se čuva na player/team profilu i prikazuje se svuda u ovom turniru.
                </p>

                <div
                    v-if="participant.participant_type === 'player'"
                    class="mt-4 grid gap-4 md:grid-cols-2"
                >
                    <div>
                        <label class="text-sm font-medium">
                            Ime
                        </label>

                        <input
                            v-model="form.first_name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                        >

                        <p
                            v-if="form.errors.first_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.first_name }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Prezime
                        </label>

                        <input
                            v-model="form.last_name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                        >

                        <p
                            v-if="form.errors.last_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.last_name }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">
                            Nadimak
                        </label>

                        <input
                            v-model="form.nickname"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                        >

                        <p
                            v-if="form.errors.nickname"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.nickname }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4"
                >
                    <label class="text-sm font-medium">
                        Naziv ekipe
                    </label>

                    <input
                        v-model="form.team_name"
                        type="text"
                        class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                    >

                    <p
                        v-if="form.errors.team_name"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.team_name }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
                <h2 class="text-lg font-medium">
                    Review
                </h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Turnir</span>
                        <span class="text-right font-medium">{{ tournament.name }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Slot</span>
                        <span class="font-medium">{{ participant.group_position }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Trenutno</span>
                        <span class="text-right font-medium">{{ participant.display_name }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Tip</span>
                        <span class="font-medium">
                            {{ participant.participant_type === 'player' ? 'Igrač' : 'Ekipa' }}
                        </span>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Čuvam...' : 'Sačuvaj izmene' }}
                </button>
            </div>
        </form>
    </div>
</template>
