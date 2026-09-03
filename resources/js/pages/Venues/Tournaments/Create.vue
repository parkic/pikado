<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronDown, Sparkles } from '@lucide/vue';
import { computed } from 'vue';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentBasicInformation from '@/components/tournaments/TournamentBasicInformation.vue';
import TournamentCreateReview from '@/components/tournaments/TournamentCreateReview.vue';
import TournamentGroupQualificationSettings from '@/components/tournaments/TournamentGroupQualificationSettings.vue';
import TournamentResourceSelector from '@/components/tournaments/TournamentResourceSelector.vue';

import { venueTournamentRoutes } from '@/lib/tournamentRoutes';
import type {
    TournamentCreateFormData,
    TournamentCreateOptions,
    TournamentSelectableResource,
} from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

type SetupPreset = {
    id: string;
    label: string;
    description: string;
    badge: string;
    groupCount: number;
    groupSize: number;
    directQualifiers: number;
    knockoutSize: number;
    repechageEnabled: boolean;
    repechageParticipants: number | null;
    repechageQualifiers: number | null;
};

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
    tournament_date: props.options.default_date,
    game_type: '301',
    match_mode: 'singles',
    group_rounds: 'single',
    group_count: 8,
    group_size: 5,
    direct_qualifiers_per_group: 4,
    repechage_enabled: false,
    repechage_participants_count: null,
    repechage_qualifiers_count: null,
    knockout_size: 32,
    public_enabled: true,
    resource_ids: props.resources.map((resource) => resource.id),
});

const presets: SetupPreset[] = [
    {
        id: 'standard-32',
        label: 'Standard 32',
        description: '8 grupa po 5, po 4 prolaze direktno.',
        badge: 'Najčešći izbor',
        groupCount: 8,
        groupSize: 5,
        directQualifiers: 4,
        knockoutSize: 32,
        repechageEnabled: false,
        repechageParticipants: null,
        repechageQualifiers: null,
    },
    {
        id: 'standard-16',
        label: 'Standard 16',
        description: '4 grupe po 5, po 4 prolaze direktno.',
        badge: 'Do 20 ljudi',
        groupCount: 4,
        groupSize: 5,
        directQualifiers: 4,
        knockoutSize: 16,
        repechageEnabled: false,
        repechageParticipants: null,
        repechageQualifiers: null,
    },
    {
        id: 'compact-8',
        label: 'Kompaktni 8',
        description: '2 grupe po 5, po 4 prolaze direktno.',
        badge: 'Brza varijanta',
        groupCount: 2,
        groupSize: 5,
        directQualifiers: 4,
        knockoutSize: 8,
        repechageEnabled: false,
        repechageParticipants: null,
        repechageQualifiers: null,
    },
    {
        id: 'repechage-16',
        label: 'Top 16 + repasaž',
        description: '4×6, 12 direktno i još 4 iz repasaža.',
        badge: 'Druga šansa',
        groupCount: 4,
        groupSize: 6,
        directQualifiers: 3,
        knockoutSize: 16,
        repechageEnabled: true,
        repechageParticipants: 8,
        repechageQualifiers: 4,
    },
];

const selectedPresetId = computed(() => {
    return (
        presets.find((preset) => {
            return (
                form.group_count === preset.groupCount &&
                form.group_size === preset.groupSize &&
                form.direct_qualifiers_per_group === preset.directQualifiers &&
                form.knockout_size === preset.knockoutSize &&
                form.repechage_enabled === preset.repechageEnabled &&
                form.repechage_participants_count ===
                    preset.repechageParticipants &&
                form.repechage_qualifiers_count === preset.repechageQualifiers
            );
        })?.id ?? null
    );
});

const totalKnockoutQualifiers = computed(() => {
    return (
        form.group_count * form.direct_qualifiers_per_group +
        (form.repechage_enabled ? (form.repechage_qualifiers_count ?? 0) : 0)
    );
});

const canSubmit = computed(() => {
    return (
        form.name.trim().length > 0 &&
        form.tournament_date.length > 0 &&
        form.knockout_size !== null &&
        totalKnockoutQualifiers.value === form.knockout_size
    );
});

const applyPreset = (preset: SetupPreset) => {
    form.group_count = preset.groupCount;
    form.group_size = preset.groupSize;
    form.direct_qualifiers_per_group = preset.directQualifiers;
    form.knockout_size = preset.knockoutSize;
    form.repechage_enabled = preset.repechageEnabled;
    form.repechage_participants_count = preset.repechageParticipants;
    form.repechage_qualifiers_count = preset.repechageQualifiers;
};

const updateMatchMode = (value: string) => {
    if (value !== 'singles' && value !== 'doubles') {
        return;
    }

    form.match_mode = value;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        repechage_participants_count: data.repechage_enabled
            ? data.repechage_participants_count
            : null,
        repechage_qualifiers_count: data.repechage_enabled
            ? data.repechage_qualifiers_count
            : null,
    })).post(routes.store);
};
</script>

<template>
    <Head :title="`Novi turnir - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :eyebrow="venue.name"
            title="Novi turnir"
            description="Unesi osnovne podatke i izaberi format. Sve ostalo možeš da prilagodiš po potrebi."
        >
            <template #actions>
                <Link
                    :href="routes.index"
                    class="inline-flex items-center justify-center rounded-xl border border-sidebar-border/70 px-4 py-2.5 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Odustani
                </Link>
            </template>
        </PageHeader>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex min-w-0 flex-col gap-6">
                <TournamentBasicInformation
                    :name="form.name"
                    :tournament-date="form.tournament_date"
                    :game-type="form.game_type"
                    :match-mode="form.match_mode"
                    :group-rounds="form.group_rounds"
                    :knockout-size="form.knockout_size"
                    :options="options"
                    :show-advanced="false"
                    :name-error="form.errors.name"
                    :tournament-date-error="form.errors.tournament_date"
                    :game-type-error="form.errors.game_type"
                    :match-mode-error="form.errors.match_mode"
                    @update:name="form.name = $event"
                    @update:tournament-date="form.tournament_date = $event"
                    @update:game-type="form.game_type = $event"
                    @update:match-mode="updateMatchMode"
                    @update:group-rounds="form.group_rounds = $event"
                    @update:knockout-size="form.knockout_size = $event"
                />

                <section
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-0.5 rounded-lg bg-primary/10 p-2 text-primary"
                        >
                            <Sparkles class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-lg font-medium">Izaberi format</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Gotov format popunjava grupe i prolaz dalje
                                umesto tebe.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="preset in presets"
                            :key="preset.id"
                            type="button"
                            class="relative rounded-xl border p-4 text-left transition"
                            :class="
                                selectedPresetId === preset.id
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                    : 'border-sidebar-border/70 hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border'
                            "
                            @click="applyPreset(preset)"
                        >
                            <span
                                v-if="selectedPresetId === preset.id"
                                class="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                            >
                                <Check class="size-3.5" />
                            </span>
                            <span class="text-xs font-medium text-primary">
                                {{ preset.badge }}
                            </span>
                            <span class="mt-2 block font-semibold">
                                {{ preset.label }}
                            </span>
                            <span
                                class="mt-1 block pr-5 text-sm text-muted-foreground"
                            >
                                {{ preset.description }}
                            </span>
                        </button>
                    </div>
                </section>

                <details
                    class="group rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 p-4"
                    >
                        <div>
                            <h2 class="font-medium">Napredna podešavanja</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Krugovi, tačan broj grupa, repasaž i oprema.
                            </p>
                        </div>
                        <ChevronDown
                            class="size-5 shrink-0 text-muted-foreground transition group-open:rotate-180"
                        />
                    </summary>

                    <div
                        class="space-y-6 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium">
                                    Grupna faza
                                </label>
                                <select
                                    v-model="form.group_rounds"
                                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                                >
                                    <option
                                        v-for="option in options.group_rounds"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium">
                                    Veličina nokauta
                                </label>
                                <select
                                    v-model.number="form.knockout_size"
                                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                                >
                                    <option
                                        v-for="option in options.knockout_sizes"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <TournamentGroupQualificationSettings
                            :group-count="form.group_count"
                            :group-size="form.group_size"
                            :direct-qualifiers-per-group="
                                form.direct_qualifiers_per_group
                            "
                            :repechage-enabled="form.repechage_enabled"
                            :repechage-participants-count="
                                form.repechage_participants_count
                            "
                            :repechage-qualifiers-count="
                                form.repechage_qualifiers_count
                            "
                            :knockout-size="form.knockout_size"
                            :errors="form.errors"
                            @update:group-count="form.group_count = $event"
                            @update:group-size="form.group_size = $event"
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

                        <TournamentResourceSelector
                            :resources="resources"
                            :selected-resource-ids="form.resource_ids"
                            :error="form.errors.resource_ids"
                            @update:selected-resource-ids="
                                form.resource_ids = $event
                            "
                        />
                    </div>
                </details>
            </div>

            <TournamentCreateReview
                :public-enabled="form.public_enabled"
                :tournament-date="form.tournament_date"
                :resources-count="form.resource_ids.length"
                :group-count="form.group_count"
                :group-size="form.group_size"
                :knockout-size="form.knockout_size"
                :repechage-enabled="form.repechage_enabled"
                :can-submit="canSubmit"
                :processing="form.processing"
                @update:public-enabled="form.public_enabled = $event"
            />
        </form>
    </div>
</template>
