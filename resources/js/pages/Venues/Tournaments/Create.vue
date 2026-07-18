<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentBasicInformation from '@/components/tournaments/TournamentBasicInformation.vue';
import TournamentCreateReview from '@/components/tournaments/TournamentCreateReview.vue';
import TournamentResourceSelector from '@/components/tournaments/TournamentResourceSelector.vue';

import { venueTournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentCreateFormData, TournamentCreateOptions, TournamentSelectableResource } from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    resources: TournamentSelectableResource[];
    options: TournamentCreateOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Novi turnir',
                href: '#',
            },
        ],
    },
});

const routes = venueTournamentRoutes(props.venue.slug);

const form = useForm<TournamentCreateFormData>({
    name: '',
    game_type: '301',
    match_mode: 'singles',
    group_rounds: 'single',
    knockout_size: 16,
    public_enabled: true,
    resource_ids: props.resources.map((resource) => resource.id),
});

const submit = () => {
    form.post(routes.store);
};
</script>

<template>
    <Head :title="`Novi turnir - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Novi turnir"
            description="Kreiraj osnovni draft turnira. Grupe, učesnike i mečeve dodajemo u sledećim koracima."
        >
            <template #actions>
                <Link
                    :href="routes.index"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnire
                </Link>
            </template>
        </PageHeader>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <TournamentBasicInformation
                    :name="form.name"
                    :game-type="form.game_type"
                    :match-mode="form.match_mode"
                    :group-rounds="form.group_rounds"
                    :knockout-size="form.knockout_size"
                    :options="options"
                    :name-error="form.errors.name"
                    :game-type-error="form.errors.game_type"
                    :match-mode-error="form.errors.match_mode"
                    :group-rounds-error="form.errors.group_rounds"
                    :knockout-size-error="form.errors.knockout_size"
                    @update:name="form.name = $event"
                    @update:game-type="form.game_type = $event"
                    @update:match-mode="form.match_mode = $event"
                    @update:group-rounds="form.group_rounds = $event"
                    @update:knockout-size="form.knockout_size = $event"
                />

                <TournamentResourceSelector
                    :resources="resources"
                    :selected-resource-ids="form.resource_ids"
                    :error="form.errors.resource_ids"
                    @update:selected-resource-ids="form.resource_ids = $event"
                />
            </div>

            <TournamentCreateReview
                :public-enabled="form.public_enabled"
                :resources-count="form.resource_ids.length"
                :processing="form.processing"
                @update:public-enabled="form.public_enabled = $event"
            />
        </form>
    </div>
</template>
