<script setup lang="ts">
defineProps<{
    activeGroupPosition: string | null;
    manuallySelected: boolean;
    slotError?: string;
}>();

const emit = defineEmits<{
    'clear-selected-slot': [];
}>();
</script>

<template>
    <div>
        <div
            v-if="activeGroupPosition"
            class="mt-4 rounded-lg border border-primary/30 bg-primary/5 p-4"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            manuallySelected
                                ? 'Izabrano mesto'
                                : 'Sledeće mesto'
                        }}
                    </p>

                    <p class="mt-1 text-3xl font-semibold tracking-tight">
                        {{ activeGroupPosition }}
                    </p>
                </div>

                <button
                    v-if="manuallySelected"
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('clear-selected-slot')"
                >
                    Vrati automatski
                </button>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300"
        >
            Sve grupe su popunjene.
        </div>

        <div
            v-if="slotError"
            class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-600"
        >
            {{ slotError }}
        </div>
    </div>
</template>
