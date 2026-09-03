<script setup lang="ts">
import { computed } from 'vue';

type QualificationErrors = {
    group_count?: string;
    group_size?: string;
    direct_qualifiers_per_group?: string;
    repechage_enabled?: string;
    repechage_participants_count?: string;
    repechage_qualifiers_count?: string;
    knockout_size?: string;
};

const props = defineProps<{
    groupCount: number;
    groupSize: number;
    directQualifiersPerGroup: number;
    repechageEnabled: boolean;
    repechageParticipantsCount: number | null;
    repechageQualifiersCount: number | null;
    knockoutSize: number | null;
    errors?: QualificationErrors;
}>();

const emit = defineEmits<{
    'update:groupCount': [value: number];
    'update:groupSize': [value: number];
    'update:directQualifiersPerGroup': [value: number];
    'update:repechageEnabled': [value: boolean];
    'update:repechageParticipantsCount': [value: number | null];
    'update:repechageQualifiersCount': [value: number | null];
}>();

const totalSlots = computed(() => {
    return props.groupCount * props.groupSize;
});

const directQualifiersCount = computed(() => {
    return props.groupCount * props.directQualifiersPerGroup;
});

const repechageQualifiersCount = computed(() => {
    if (!props.repechageEnabled) {
        return 0;
    }

    return props.repechageQualifiersCount ?? 0;
});

const totalKnockoutQualifiers = computed(() => {
    return directQualifiersCount.value + repechageQualifiersCount.value;
});

const knockoutConfigurationIsValid = computed(() => {
    if (props.knockoutSize === null) {
        return false;
    }

    return totalKnockoutQualifiers.value === props.knockoutSize;
});

const repechagePerGroup = computed<number | null>(() => {
    if (
        !props.repechageEnabled ||
        !props.repechageParticipantsCount ||
        props.groupCount < 1 ||
        props.repechageParticipantsCount % props.groupCount !== 0
    ) {
        return null;
    }

    return props.repechageParticipantsCount / props.groupCount;
});

const requiredNumberValue = (event: Event): number => {
    const target = event.target as HTMLInputElement;

    return Number(target.value);
};

const nullableNumberValue = (event: Event): number | null => {
    const target = event.target as HTMLInputElement;

    if (target.value === '') {
        return null;
    }

    return Number(target.value);
};

const updateGroupCount = (event: Event) => {
    emit('update:groupCount', requiredNumberValue(event));
};

const updateGroupSize = (event: Event) => {
    emit('update:groupSize', requiredNumberValue(event));
};

const updateDirectQualifiersPerGroup = (event: Event) => {
    emit('update:directQualifiersPerGroup', requiredNumberValue(event));
};

const updateRepechageEnabled = (event: Event) => {
    const target = event.target as HTMLInputElement;

    emit('update:repechageEnabled', target.checked);
};

const updateRepechageParticipantsCount = (event: Event) => {
    emit('update:repechageParticipantsCount', nullableNumberValue(event));
};

const updateRepechageQualifiersCount = (event: Event) => {
    emit('update:repechageQualifiersCount', nullableNumberValue(event));
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">Grupe i prolaz dalje</h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Podesi kapacitet grupne faze i način kvalifikovanja za nokaut.
            </p>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div>
                <label for="group-count" class="text-sm font-medium">
                    Broj grupa
                </label>

                <input
                    id="group-count"
                    :value="groupCount"
                    type="number"
                    min="1"
                    max="32"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                    @input="updateGroupCount"
                />

                <p v-if="errors?.group_count" class="mt-1 text-sm text-red-600">
                    {{ errors.group_count }}
                </p>
            </div>

            <div>
                <label for="group-size" class="text-sm font-medium">
                    Učesnika po grupi
                </label>

                <input
                    id="group-size"
                    :value="groupSize"
                    type="number"
                    min="2"
                    max="16"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                    @input="updateGroupSize"
                />

                <p v-if="errors?.group_size" class="mt-1 text-sm text-red-600">
                    {{ errors.group_size }}
                </p>
            </div>

            <div>
                <label for="direct-qualifiers" class="text-sm font-medium">
                    Direktno prolazi po grupi
                </label>

                <input
                    id="direct-qualifiers"
                    :value="directQualifiersPerGroup"
                    type="number"
                    min="0"
                    :max="groupSize"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                    @input="updateDirectQualifiersPerGroup"
                />

                <p
                    v-if="errors?.direct_qualifiers_per_group"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ errors.direct_qualifiers_per_group }}
                </p>
            </div>
        </div>

        <div
            class="mt-5 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <label class="flex cursor-pointer items-start gap-3">
                <input
                    :checked="repechageEnabled"
                    type="checkbox"
                    class="mt-1 size-4 rounded border-sidebar-border"
                    @change="updateRepechageEnabled"
                />

                <span>
                    <span class="block text-sm font-medium">
                        Uključi repasaž
                    </span>

                    <span class="mt-1 block text-sm text-muted-foreground">
                        Deo učesnika koji nisu prošli direktno dobija dodatnu
                        šansu za plasman u nokaut.
                    </span>
                </span>
            </label>

            <p
                v-if="errors?.repechage_enabled"
                class="mt-2 text-sm text-red-600"
            >
                {{ errors.repechage_enabled }}
            </p>

            <div
                v-if="repechageEnabled"
                class="mt-4 grid gap-4 border-t border-sidebar-border/70 pt-4 md:grid-cols-2 dark:border-sidebar-border"
            >
                <div>
                    <label
                        for="repechage-participants"
                        class="text-sm font-medium"
                    >
                        Učesnika u repasažu
                    </label>

                    <input
                        id="repechage-participants"
                        :value="repechageParticipantsCount ?? ''"
                        type="number"
                        min="1"
                        max="128"
                        class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                        @input="updateRepechageParticipantsCount"
                    />

                    <p
                        v-if="errors?.repechage_participants_count"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.repechage_participants_count }}
                    </p>

                    <p
                        v-else-if="
                            repechageParticipantsCount &&
                            repechagePerGroup === null
                        "
                        class="mt-1 text-sm text-yellow-700 dark:text-yellow-300"
                    >
                        Broj mora biti deljiv sa brojem grupa.
                    </p>

                    <p
                        v-else-if="repechagePerGroup !== null"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Iz svake grupe u repasaž ulazi
                        {{ repechagePerGroup }}
                        učesnika.
                    </p>
                </div>

                <div>
                    <label
                        for="repechage-qualifiers"
                        class="text-sm font-medium"
                    >
                        Prolazi iz repasaža
                    </label>

                    <input
                        id="repechage-qualifiers"
                        :value="repechageQualifiersCount ?? ''"
                        type="number"
                        min="1"
                        :max="repechageParticipantsCount ?? 64"
                        class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                        @input="updateRepechageQualifiersCount"
                    />

                    <p
                        v-if="errors?.repechage_qualifiers_count"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.repechage_qualifiers_count }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Ukupno mesta</p>

                <p class="mt-1 text-xl font-semibold">
                    {{ totalSlots }}
                </p>
            </div>

            <div
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Direktno prolazi</p>

                <p class="mt-1 text-xl font-semibold">
                    {{ directQualifiersCount }}
                </p>
            </div>

            <div
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Iz repasaža</p>

                <p class="mt-1 text-xl font-semibold">
                    {{ repechageQualifiersCount }}
                </p>
            </div>

            <div
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Ukupno u nokautu</p>

                <p class="mt-1 text-xl font-semibold">
                    {{ totalKnockoutQualifiers }}
                </p>
            </div>
        </div>

        <div
            v-if="knockoutSize === null"
            class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-800 dark:text-yellow-200"
        >
            Izaberi veličinu nokauta da bismo mogli da proverimo konfiguraciju.
        </div>

        <div
            v-else-if="knockoutConfigurationIsValid"
            class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-800 dark:text-emerald-200"
        >
            Konfiguracija je ispravna:
            {{ totalKnockoutQualifiers }}
            učesnika ulazi u Top
            {{ knockoutSize }}.
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-800 dark:text-yellow-200"
        >
            Trenutno u nokaut prolazi {{ totalKnockoutQualifiers }} učesnika,
            dok je izabran Top {{ knockoutSize }}. Ove vrednosti moraju da budu
            jednake.
        </div>

        <p v-if="errors?.knockout_size" class="mt-2 text-sm text-red-600">
            {{ errors.knockout_size }}
        </p>
    </div>
</template>
