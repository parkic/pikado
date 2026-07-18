<script setup lang="ts">
defineProps<{
    tournamentName: string;
    statusLabel: string;
    groupCount: number;
    groupSize: number;
    processing: boolean;
    disabled: boolean;
}>();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
        <h2 class="text-lg font-medium">
            Review
        </h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Turnir
                </span>

                <span class="font-medium text-right">
                    {{ tournamentName }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Status
                </span>

                <span class="font-medium">
                    {{ statusLabel }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Broj grupa
                </span>

                <span class="font-medium">
                    {{ groupCount }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Mesta po grupi
                </span>

                <span class="font-medium">
                    {{ groupSize }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Ukupno mesta
                </span>

                <span class="font-medium">
                    {{ groupCount * groupSize }}
                </span>
            </div>
        </div>

        <button
            type="submit"
            :disabled="processing || disabled"
            class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
        >
            {{ processing ? 'Čuvam...' : 'Sačuvaj grupe' }}
        </button>
    </div>
</template>
