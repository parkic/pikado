<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    groupsCount: number;
    participantsCount: number;
    directQualifiersPerGroup: string | number | null;
    repechageEnabled: boolean;
    repechageParticipantsCount: string | number | null;
    repechageQualifiersCount: string | number | null;
}>();

const repechagePerGroup = computed<number | null>(() => {
    if (
        !props.repechageEnabled ||
        !props.repechageParticipantsCount ||
        props.groupsCount <= 0
    ) {
        return null;
    }

    const participantsCount = Number(props.repechageParticipantsCount);

    if (
        !Number.isFinite(participantsCount) ||
        participantsCount % props.groupsCount !== 0
    ) {
        return null;
    }

    return participantsCount / props.groupsCount;
});
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <h2 class="text-lg font-medium">Pregled</h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Grupe </span>

                <span class="font-medium">
                    {{ groupsCount }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Učesnici </span>

                <span class="font-medium">
                    {{ participantsCount }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Direktno po grupi </span>

                <span class="font-medium">
                    {{ directQualifiersPerGroup }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Repasaž </span>

                <span class="font-medium">
                    {{ repechageEnabled ? 'Da' : 'Ne' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Ukupno u repasažu </span>

                <span class="font-medium">
                    {{ repechageParticipantsCount ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Repasaž po grupi </span>

                <span class="font-medium">
                    {{ repechagePerGroup ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Iz repasaža dalje </span>

                <span class="font-medium">
                    {{ repechageQualifiersCount ?? '-' }}
                </span>
            </div>
        </div>

        <div
            class="mt-5 rounded-lg border border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Primer za grupu od 4 učesnika i 2 direktna prolaza: prva 2 imaju
            status Direktan prolaz, ostali Repasaž ili Ispao.
        </div>
    </div>
</template>
