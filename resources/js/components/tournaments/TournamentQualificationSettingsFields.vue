<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    directQualifiersPerGroup: number;
    repechageEnabled: boolean;
    repechageParticipantsCount: number | null;
    repechageQualifiersCount: number | null;
    directQualifiersError?: string;
    repechageEnabledError?: string;
    repechageParticipantsError?: string;
    repechageQualifiersError?: string;
}>();

const emit = defineEmits<{
    'update:directQualifiersPerGroup': [value: number];
    'update:repechageEnabled': [value: boolean];
    'update:repechageParticipantsCount': [value: number | null];
    'update:repechageQualifiersCount': [value: number | null];
}>();

const directQualifiersPerGroupModel = computed<number>({
    get: () => props.directQualifiersPerGroup,
    set: (value) =>
        emit('update:directQualifiersPerGroup', value),
});

const repechageEnabledModel = computed<boolean>({
    get: () => props.repechageEnabled,
    set: (value) =>
        emit('update:repechageEnabled', value),
});

const repechageParticipantsCountModel = computed<number | null>({
    get: () => props.repechageParticipantsCount,
    set: (value) =>
        emit('update:repechageParticipantsCount', value),
});

const repechageQualifiersCountModel = computed<number | null>({
    get: () => props.repechageQualifiersCount,
    set: (value) =>
        emit('update:repechageQualifiersCount', value),
});
</script>

<template>
    <div>
        <div>
            <h2 class="text-lg font-medium">
                Pravila prolaza
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Ova podešavanja utiču na status u tabeli grupa:
                Direktan prolaz, Repasaž ili Ispao.
            </p>
        </div>

        <div class="mt-5 space-y-5">
            <div>
                <label class="text-sm font-medium">
                    Direktno prolazi po grupi
                </label>

                <input
                    v-model.number="directQualifiersPerGroupModel"
                    type="number"
                    min="0"
                    max="16"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                >

                <p class="mt-1 text-xs text-muted-foreground">
                    Primer: ako staviš 2, prva dva iz svake grupe
                    imaju status Direktan prolaz.
                </p>

                <p
                    v-if="directQualifiersError"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ directQualifiersError }}
                </p>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <label class="flex items-start gap-3">
                    <input
                        v-model="repechageEnabledModel"
                        type="checkbox"
                        class="mt-1"
                    >

                    <span>
                        <span class="block text-sm font-medium">
                            Repasaž uključen
                        </span>

                        <span class="mt-1 block text-xs text-muted-foreground">
                            Ako je uključeno, učesnici koji nisu
                            direktno prošli biće označeni kao Repasaž.
                            Ako nije uključeno, biće označeni kao Ispao.
                        </span>
                    </span>
                </label>

                <p
                    v-if="repechageEnabledError"
                    class="mt-2 text-sm text-red-600"
                >
                    {{ repechageEnabledError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium">
                    Ukupno učesnika ide u repasaž
                </label>

                <input
                    v-model.number="repechageParticipantsCountModel"
                    type="number"
                    min="0"
                    max="128"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                    placeholder="Primer: 8"
                >

                <p class="mt-1 text-xs text-muted-foreground">
                    Ovaj broj se deli ravnomerno po grupama. Primer:
                    8 grupa i ukupno 8 u repasažu znači da iz svake
                    grupe još 1 učesnik ide u repasaž.
                </p>

                <p
                    v-if="repechageParticipantsError"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ repechageParticipantsError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium">
                    Broj učesnika koji prolazi iz repasaža
                </label>

                <input
                    v-model.number="repechageQualifiersCountModel"
                    type="number"
                    min="0"
                    max="64"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                    placeholder="Opciono"
                >

                <p class="mt-1 text-xs text-muted-foreground">
                    Ovo još ne koristimo za generisanje repasaža,
                    ali ga čuvamo za sledeći korak.
                </p>

                <p
                    v-if="repechageQualifiersError"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ repechageQualifiersError }}
                </p>
            </div>
        </div>
    </div>
</template>
