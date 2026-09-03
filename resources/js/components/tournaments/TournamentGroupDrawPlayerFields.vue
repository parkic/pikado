<script setup lang="ts">
import { ChevronDown, CornerDownLeft } from '@lucide/vue';
import { ref, watch } from 'vue';
import type { TournamentGroupDrawPlayer } from '@/types/tournament';

const props = defineProps<{
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

const highlightedIndex = ref(0);
const listOpen = ref(false);

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
    listOpen.value = true;
};

const choosePlayer = (player: TournamentGroupDrawPlayer): void => {
    emit('choose-player', player);
    listOpen.value = false;
};

const moveHighlight = (direction: 1 | -1): void => {
    if (!props.filteredPlayers.length) {
        return;
    }

    listOpen.value = true;
    highlightedIndex.value =
        (highlightedIndex.value + direction + props.filteredPlayers.length) %
        props.filteredPlayers.length;
};

const handleSearchKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveHighlight(1);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveHighlight(-1);

        return;
    }

    if (event.key === 'Enter' && listOpen.value) {
        const player = props.filteredPlayers[highlightedIndex.value];

        if (player) {
            event.preventDefault();
            choosePlayer(player);
        }

        return;
    }

    if (event.key === 'Escape') {
        listOpen.value = false;
    }
};

watch(
    () => [props.playerSearch, props.filteredPlayers] as const,
    () => {
        highlightedIndex.value = 0;
    },
);

watch(
    () => props.selectedPlayer,
    (selectedPlayer) => {
        if (selectedPlayer) {
            listOpen.value = false;
        }
    },
);

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

            <div class="relative mt-2">
                <input
                    :value="playerSearch"
                    type="text"
                    role="combobox"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="player-search-results"
                    :aria-expanded="
                        listOpen &&
                        Boolean(playerSearch && filteredPlayers.length)
                    "
                    :aria-activedescendant="
                        listOpen && filteredPlayers[highlightedIndex]
                            ? `player-option-${filteredPlayers[highlightedIndex].id}`
                            : undefined
                    "
                    class="w-full rounded-lg border border-sidebar-border/70 bg-background py-2.5 pr-10 pl-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 dark:border-sidebar-border"
                    placeholder="Kucaj ime, prezime ili nadimak..."
                    @input="updatePlayerSearch"
                    @focus="listOpen = true"
                    @keydown="handleSearchKeydown"
                />
                <ChevronDown
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <div
                v-if="listOpen && playerSearch && filteredPlayers.length"
                id="player-search-results"
                role="listbox"
                class="mt-2 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-1 shadow-xl dark:border-sidebar-border"
            >
                <button
                    v-for="(player, index) in filteredPlayers"
                    :id="`player-option-${player.id}`"
                    :key="player.id"
                    type="button"
                    role="option"
                    :aria-selected="index === highlightedIndex"
                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition"
                    :class="
                        index === highlightedIndex
                            ? 'bg-primary/10 text-foreground'
                            : 'hover:bg-muted'
                    "
                    @mouseenter="highlightedIndex = index"
                    @mousedown.prevent
                    @click="choosePlayer(player)"
                >
                    <span class="font-medium">
                        {{ player.display_name }}
                    </span>
                    <CornerDownLeft
                        v-if="index === highlightedIndex"
                        class="size-4 shrink-0 text-primary"
                    />
                </button>
            </div>

            <p
                v-if="playerSearch && filteredPlayers.length && !selectedPlayer"
                class="mt-2 text-xs text-muted-foreground"
            >
                Koristi ↑ ↓ za izbor i Enter za potvrdu.
            </p>

            <div
                v-if="
                    playerSearch && !filteredPlayers.length && !selectedPlayer
                "
                class="mt-2 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Nema pronađenih igrača. Nastavi ručni unos ispod i napravićemo
                novog igrača.
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

            <p v-if="existingPlayerIdError" class="mt-1 text-sm text-red-600">
                {{ existingPlayerIdError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium"> Ime </label>

            <input
                :value="firstName"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                placeholder="Jelena"
                @input="updateFirstName"
            />

            <p v-if="firstNameError" class="mt-1 text-sm text-red-600">
                {{ firstNameError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium"> Prezime </label>

            <input
                :value="lastName"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                placeholder="Mladenović"
                @input="updateLastName"
            />

            <p v-if="lastNameError" class="mt-1 text-sm text-red-600">
                {{ lastNameError }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium"> Nadimak </label>

            <input
                :value="nickname"
                type="text"
                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                placeholder="Jeca"
                @input="updateNickname"
            />

            <p v-if="nicknameError" class="mt-1 text-sm text-red-600">
                {{ nicknameError }}
            </p>
        </div>
    </div>
</template>
