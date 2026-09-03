<script setup lang="ts">
const props = defineProps<{
    publicEnabled: boolean;
    tournamentDate: string;
    resourcesCount: number;
    groupCount: number;
    groupSize: number;
    knockoutSize: number | null;
    repechageEnabled: boolean;
    canSubmit: boolean;
    processing: boolean;
}>();

const emit = defineEmits<{
    'update:publicEnabled': [value: boolean];
}>();

const formattedTournamentDate = () => {
    const [year, month, day] = props.tournamentDate.split('-');

    return year && month && day ? `${day}.${month}.${year}.` : '—';
};

const updatePublicEnabled = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:publicEnabled', input.checked);
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 xl:sticky xl:top-4 xl:self-start dark:border-sidebar-border"
    >
        <h2 class="text-lg font-medium">Pregled</h2>

        <p class="mt-1 text-sm text-muted-foreground">
            Posle kreiranja odmah prelaziš na unos učesnika.
        </p>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Status </span>

                <span class="font-medium"> Priprema </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Datum </span>

                <span class="font-medium">
                    {{ formattedTournamentDate() }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Kapacitet </span>

                <span class="font-medium">
                    {{ groupCount * groupSize }} učesnika
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Grupe </span>

                <span class="font-medium">
                    {{ groupCount }} × {{ groupSize }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Završnica </span>

                <span class="font-medium"> Top {{ knockoutSize ?? '-' }} </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Repasaž </span>

                <span class="font-medium">
                    {{ repechageEnabled ? 'Uključen' : 'Bez repasaža' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground"> Oprema </span>

                <span class="font-medium">
                    {{ resourcesCount }}
                </span>
            </div>
        </div>

        <label class="mt-5 flex cursor-pointer items-center gap-3 text-sm">
            <input
                type="checkbox"
                :checked="publicEnabled"
                @change="updatePublicEnabled"
            />

            <span>Javni prikaz uključen</span>
        </label>

        <button
            type="submit"
            :disabled="processing || !canSubmit"
            class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
        >
            {{ processing ? 'Kreiram...' : 'Kreiraj i dodaj učesnike' }}
        </button>

        <p
            v-if="!props.canSubmit"
            class="mt-2 text-center text-xs text-muted-foreground"
        >
            Unesi naziv i izaberi ispravnu konfiguraciju.
        </p>
    </div>
</template>
