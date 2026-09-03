<script setup lang="ts">
import type { TournamentGroupDrawTeam } from '@/types/tournament';

defineProps<{
    teamSearch: string;
    filteredTeams: TournamentGroupDrawTeam[];
    selectedTeam?: TournamentGroupDrawTeam;
    teamName: string;
    existingTeamIdError?: string;
    teamNameError?: string;
}>();

const emit = defineEmits<{
    'update:teamSearch': [value: string];
    'update:teamName': [value: string];
    'choose-team': [team: TournamentGroupDrawTeam];
    'clear-team': [];
}>();

const updateTeamSearch = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:teamSearch', input.value);
};

const updateTeamName = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:teamName', input.value);
};
</script>

<template>
    <div class="mt-5 space-y-4">
        <div>
            <label class="text-sm font-medium">
                Pretraga postojeće ekipe
            </label>

            <input
                :value="teamSearch"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                placeholder="Kucaj naziv ekipe..."
                @input="updateTeamSearch"
            />

            <div
                v-if="teamSearch && filteredTeams.length"
                class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <button
                    v-for="team in filteredTeams"
                    :key="team.id"
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                    @click="emit('choose-team', team)"
                >
                    {{ team.name }}
                </button>
            </div>

            <div
                v-if="teamSearch && !filteredTeams.length && !selectedTeam"
                class="mt-2 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Nema pronađenih ekipa. Nastavi ručni unos ispod i napravićemo
                novu ekipu.
            </div>

            <div
                v-if="selectedTeam"
                class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"
            >
                <span>
                    Izabrana ekipa:
                    <strong>{{ selectedTeam.name }}</strong>
                </span>

                <button
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('clear-team')"
                >
                    Unesi novu
                </button>
            </div>

            <p v-if="existingTeamIdError" class="mt-1 text-sm text-red-600">
                {{ existingTeamIdError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium"> Naziv ekipe </label>

            <input
                :value="teamName"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                placeholder="SB22"
                @input="updateTeamName"
            />

            <p v-if="teamNameError" class="mt-1 text-sm text-red-600">
                {{ teamNameError }}
            </p>
        </div>
    </div>
</template>
