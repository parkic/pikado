<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentQualificationReview from '@/components/tournaments/TournamentQualificationReview.vue';
import TournamentQualificationSettingsFields from '@/components/tournaments/TournamentQualificationSettingsFields.vue';
import { tournamentRoutes } from '@/lib/tournamentRoutes';
import type {
    TournamentQualificationFormData,
    TournamentQualificationSetupData,
} from '@/types/tournament';

import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentQualificationSetupData;
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

const routes = tournamentRoutes(
    props.venue.slug,
    props.tournament.slug,
);

const form = useForm<TournamentQualificationFormData>({
    direct_qualifiers_per_group:
        props.tournament.settings.direct_qualifiers_per_group ?? 2,
    repechage_enabled:
        props.tournament.settings.repechage_enabled ?? true,
    repechage_participants_count:
        props.tournament.settings.repechage_participants_count ?? null,
    repechage_qualifiers_count:
        props.tournament.settings.repechage_qualifiers_count ?? null,
});

const submit = () => {
    form.post(routes.qualificationSetup);
};
</script>

<template>
    <Head :title="`Prolaz iz grupe - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Podešavanje prolaza iz grupe"
            description="Ovde podešavaš koliko učesnika iz svake grupe ide direktno dalje i da li ostali idu u repasaž."
        >
            <template #actions>
                <Link
                    :href="routes.standings"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <Link
                    :href="routes.show"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <form
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                @submit.prevent="submit"
            >
                <TournamentQualificationSettingsFields
                    :direct-qualifiers-per-group="form.direct_qualifiers_per_group"
                    :repechage-enabled="form.repechage_enabled"
                    :repechage-participants-count="form.repechage_participants_count"
                    :repechage-qualifiers-count="form.repechage_qualifiers_count"
                    :direct-qualifiers-error="form.errors.direct_qualifiers_per_group"
                    :repechage-enabled-error="form.errors.repechage_enabled"
                    :repechage-participants-error="form.errors.repechage_participants_count"
                    :repechage-qualifiers-error="form.errors.repechage_qualifiers_count"
                    @update:direct-qualifiers-per-group="
                        form.direct_qualifiers_per_group = $event
                    "
                    @update:repechage-enabled="
                        form.repechage_enabled = $event
                    "
                    @update:repechage-participants-count="
                        form.repechage_participants_count = $event
                    "
                    @update:repechage-qualifiers-count="
                        form.repechage_qualifiers_count = $event
                    "
                />

                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Sačuvaj podešavanja
                    </button>

                    <Link
                        :href="routes.standings"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    >
                        Otkaži
                    </Link>
                </div>
            </form>

            <TournamentQualificationReview
                :groups-count="tournament.groups_count"
                :participants-count="tournament.participants_count"
                :direct-qualifiers-per-group="form.direct_qualifiers_per_group"
                :repechage-enabled="form.repechage_enabled"
                :repechage-participants-count="form.repechage_participants_count"
                :repechage-qualifiers-count="form.repechage_qualifiers_count"
            />
        </div>
    </div>
</template>
