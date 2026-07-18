<script setup lang="ts">
import type { TournamentGroupDrawPlayer } from '@/types/tournament';

defineProps<{
    playerSearch: string;
    filteredPlayers: TournamentGroupDrawPlayer[];
    selectedPlayer?: TournamentGroupDrawPlayer;
    firstName: string;
    lastName: string;
    nickname: string;
    existingPlayerIdError?: string;
    firstNameError?: string;
    lastNameError?: string;
    nicknameError?: string;
}>();

const emit = defineEmits<{
    'update:playerSearch': [value: string];
    'update:firstName': [value: string];
    'update:lastName': [value: string];
    'update:nickname': [value: string];
    'choose-player': [player: TournamentGroupDrawPlayer];
    'clear-player': [];
}>();

const updatePlayerSearch = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:playerSearch', input.value);
};

const updateFirstName = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:firstName', input.value);
};

const updateLastName = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:lastName', input.value);
};

const updateNickname = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:nickname', input.value);
};
</script>

<template>
    <div class="mt-5 space-y-4">
        <div>
            <label class="text-sm font-medium">
                Pretraga postojećeg igrača
            </label>

            <input
                :value="playerSearch"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                placeholder="Kucaj ime, prezime ili nadimak..."
                @input="updatePlayerSearch"
            >

            <div
                v-if="playerSearch && filteredPlayers.length"
                class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <button
                    v-for="player in filteredPlayers"
                    :key="player.id"
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                    @click="emit('choose-player', player)"
                >
                    {{ player.display_name }}
                </button>
            </div>

            <div
                v-if="
                    playerSearch
                        && !filteredPlayers.length
                        && !selectedPlayer
                "
                class="mt-2 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Nema pronađenih igrača. Nastavi ručni unos ispod i
                napravićemo novog igrača.
            </div>

            <div
                v-if="selectedPlayer"
                class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"
            >
                <span>
                    Izabran:
                    <strong>{{ selectedPlayer.display_name }}</strong>
                </span>

                <button
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('clear-player')"
                >
                    Unesi novog
                </button>
            </div>

            <p
                v-if="existingPlayerIdError"
                class="mt-1 text-sm text-red-600"
            >
                {{ existingPlayerIdError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium">
                Ime
            </label>

            <input
                :value="firstName"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                placeholder="Jelena"
                @input="updateFirstName"
            >

            <p
                v-if="firstNameError"
                class="mt-1 text-sm text-red-600"
            >
                {{ firstNameError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium">
                Prezime
            </label>

            <input
                :value="lastName"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                placeholder="Mladenović"
                @input="updateLastName"
            >

            <p
                v-if="lastNameError"
                class="mt-1 text-sm text-red-600"
            >
                {{ lastNameError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium">
                Nadimak
            </label>

            <input
                :value="nickname"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                placeholder="Jeca"
                @input="updateNickname"
            >

            <p
                v-if="nicknameError"
                class="mt-1 text-sm text-red-600"
            >
                {{ nicknameError }}
            </p>
        </div>
    </div>
</template>
