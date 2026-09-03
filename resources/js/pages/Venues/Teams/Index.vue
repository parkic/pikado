<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmAction } from '@/composables/useConfirmDialog';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type Team = {
    id: number;
    name: string;
    notes: string | null;
    is_active: boolean;
};

const props = defineProps<{
    venue: Venue;
    teams: Team[];
}>();

const deleteTeam = async (team: Team) => {
    if (
        !(await confirmAction({
            title: 'Obriši tim?',
            description: `${team.name} će biti uklonjen iz baze timova ovog lokala.`,
            confirmLabel: 'Obriši tim',
            variant: 'destructive',
        }))
    ) {
        return;
    }

    router.delete(`/venues/${props.venue.slug}/teams/${team.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teams',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`${venue.name} Teams`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Timovi
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Lista timova za ovaj lokal. Kasnije će se timovi birati za
                    ekipne turnire.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="`/venues/${venue.slug}/dashboard`"
                    class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na dashboard
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/teams/create`"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Dodaj tim
                </Link>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div
                v-if="teams.length"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Naziv</th>
                            <th class="px-4 py-3 font-medium">Napomena</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Akcije
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="team in teams"
                            :key="team.id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3">
                                {{ team.name }}
                            </td>

                            <td class="px-4 py-3 text-muted-foreground">
                                {{ team.notes ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    v-if="team.is_active"
                                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium"
                                >
                                    Aktivan
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    Neaktivan
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="`/venues/${venue.slug}/teams/${team.id}/edit`"
                                        class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                                    >
                                        Izmeni
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                        @click="deleteTeam(team)"
                                    >
                                        Obriši
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Ovaj lokal još nema timove.
            </div>
        </div>
    </div>
</template>
