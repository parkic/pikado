<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    groupCount: number;
    groupSize: number;
    locked: boolean;
    groupCountError?: string;
    groupSizeError?: string;
}>();

const emit = defineEmits<{
    'update:groupCount': [value: number];
    'update:groupSize': [value: number];
}>();

const groupCountModel = computed<number>({
    get: () => props.groupCount,
    set: (value) => emit('update:groupCount', value),
});

const groupSizeModel = computed<number>({
    get: () => props.groupSize,
    set: (value) => emit('update:groupSize', value),
});
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <h2 class="text-lg font-medium">
            Grupe
        </h2>

        <p class="mt-1 text-sm text-muted-foreground">
            Za sada pravimo samo prazne grupe. Učesnike ćemo dodavati u sledećem koraku.
        </p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium">
                    Broj grupa
                </label>

                <input
                    v-model.number="groupCountModel"
                    type="number"
                    min="1"
                    max="32"
                    :disabled="locked"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary disabled:cursor-not-allowed disabled:opacity-60 dark:border-sidebar-border"
                >

                <p
                    v-if="groupCountError"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ groupCountError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium">
                    Broj mesta po grupi
                </label>

                <input
                    v-model.number="groupSizeModel"
                    type="number"
                    min="2"
                    max="16"
                    :disabled="locked"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary disabled:cursor-not-allowed disabled:opacity-60 dark:border-sidebar-border"
                >

                <p
                    v-if="groupSizeError"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ groupSizeError }}
                </p>
            </div>
        </div>

        <div
            v-if="locked"
            class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-700 dark:text-yellow-300"
        >
            Ovaj turnir već ima učesnike. Promena grupa je zaključana da ne bismo pokvarili raspored.
        </div>
    </div>
</template>
