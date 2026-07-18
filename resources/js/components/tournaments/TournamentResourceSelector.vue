<script setup lang="ts">
import type { TournamentSelectableResource } from '@/types/tournament';

const props = defineProps<{
    resources: TournamentSelectableResource[];
    selectedResourceIds: number[];
    error?: string;
}>();

const emit = defineEmits<{
    'update:selectedResourceIds': [value: number[]];
}>();

const toggleResource = (
    resourceId: number,
    event: Event,
) => {
    const input = event.target as HTMLInputElement;

    if (input.checked) {
        emit(
            'update:selectedResourceIds',
            [...props.selectedResourceIds, resourceId],
        );

        return;
    }

    emit(
        'update:selectedResourceIds',
        props.selectedResourceIds.filter(
            (selectedId) => selectedId !== resourceId,
        ),
    );
};
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <h2 class="text-lg font-medium">
            Resources za turnir
        </h2>

        <p class="mt-1 text-sm text-muted-foreground">
            Izabrani resources će biti kopirani iz podešavanja lokala u ovaj konkretan turnir.
        </p>

        <div
            v-if="resources.length"
            class="mt-4 grid gap-3 md:grid-cols-2"
        >
            <label
                v-for="resource in resources"
                :key="resource.id"
                class="flex cursor-pointer items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 transition hover:bg-muted dark:border-sidebar-border"
            >
                <input
                    type="checkbox"
                    :checked="selectedResourceIds.includes(resource.id)"
                    class="mt-1"
                    @change="toggleResource(resource.id, $event)"
                >

                <span>
                    <span class="block text-sm font-medium">
                        {{ resource.name }}
                    </span>

                    <span class="mt-1 block text-xs text-muted-foreground">
                        {{ resource.type_label }} · redosled {{ resource.sort_order }}
                    </span>
                </span>
            </label>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Ovaj lokal još nema aktivne resources. Turnir možeš napraviti, ali neće imati opremu dok je ne dodaš.
        </div>

        <p
            v-if="error"
            class="mt-3 text-sm text-red-600"
        >
            {{ error }}
        </p>
    </div>
</template>
