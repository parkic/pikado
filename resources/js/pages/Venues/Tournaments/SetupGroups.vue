<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentCurrentGroups from '@/components/tournaments/TournamentCurrentGroups.vue';
import TournamentGroupSettingsFields from '@/components/tournaments/TournamentGroupSettingsFields.vue';
import TournamentGroupsSetupReview from '@/components/tournaments/TournamentGroupsSetupReview.vue';

import { tournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentGroupsSetupData, TournamentGroupsSetupFormData } from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentGroupsSetupData;
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

const routes = tournamentRoutes(
    props.venue.slug,
    props.tournament.slug,
);

const form = useForm<TournamentGroupsSetupFormData>({
    group_count: props.tournament.settings.group_count ?? 4,
    group_size: props.tournament.settings.group_size ?? 4,
});

const submit = () => {
    form.post(routes.groupsSetup);
};
</script>

<template>
    <Head :title="`Setup grupa - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Setup grupa"
            :description="`Podesi broj grupa i broj mesta po grupi za turnir: ${tournament.name}.`"
        >
            <template #actions>
                <Link
                    :href="routes.show"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </template>
        </PageHeader>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <TournamentGroupSettingsFields
                    :group-count="form.group_count"
                    :group-size="form.group_size"
                    :locked="tournament.participants_count > 0"
                    :group-count-error="form.errors.group_count"
                    :group-size-error="form.errors.group_size"
                    @update:group-count="form.group_count = $event"
                    @update:group-size="form.group_size = $event"
                />

                <TournamentCurrentGroups
                    :groups="tournament.groups"
                />
            </div>

            <TournamentGroupsSetupReview
                :tournament-name="tournament.name"
                :status-label="tournament.status_label"
                :group-count="form.group_count"
                :group-size="form.group_size"
                :processing="form.processing"
                :disabled="tournament.participants_count > 0"
            />
        </form>
    </div>
</template>
