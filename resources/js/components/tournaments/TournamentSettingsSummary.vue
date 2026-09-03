<script setup lang="ts">
import type {
    TournamentGroupRounds,
    TournamentSettings,
} from '@/types/tournament';

defineProps<{
    statusLabel: string;
    groupRounds: TournamentGroupRounds;
    settings: TournamentSettings;
    scoringMode: string;
    knockoutSize: number | null;
    publicEnabled: boolean;
    tournamentDate: string | null;
    createdBy: string | null;
    createdAt: string | null;
}>();

const scoringModeLabel = (value: string): string => {
    if (value === 'points_difference') {
        return 'Razlika poena';
    }

    if (value === 'winner_only') {
        return 'Samo pobednik';
    }

    return value;
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <h2 class="text-lg font-medium">Podešavanja</h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Status </span>

                <span class="font-medium">
                    {{ statusLabel }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Datum turnira </span>

                <span class="font-medium">
                    {{ tournamentDate ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Grupna faza </span>

                <span class="font-medium">
                    {{ groupRounds === 'single' ? 'Jednokružno' : 'Dvokružno' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Broj grupa </span>

                <span class="font-medium">
                    {{ settings.group_count ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Mesta po grupi </span>

                <span class="font-medium">
                    {{ settings.group_size ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Računanje rezultata </span>

                <span class="font-medium">
                    {{ scoringModeLabel(scoringMode) }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Nokaut </span>

                <span class="font-medium">
                    {{ knockoutSize ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Javni prikaz </span>

                <span class="font-medium">
                    {{ publicEnabled ? 'Uključen' : 'Isključen' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Repasaž </span>

                <span class="font-medium">
                    {{ settings.repechage_enabled ? 'Da' : 'Ne' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Bez revanša iz iste grupe
                </span>

                <span class="font-medium">
                    {{ settings.avoid_same_group_rematch ? 'Da' : 'Ne' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Kreirao </span>

                <span class="font-medium">
                    {{ createdBy ?? '-' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Kreiran </span>

                <span class="font-medium">
                    {{ createdAt ?? '-' }}
                </span>
            </div>
        </div>
    </div>
</template>
