<script setup lang="ts">
withDefaults(
    defineProps<{
        eyebrow?: string | null;
        title: string;
        description?: string | null;
    }>(),
    {
        eyebrow: null,
        description: null,
    },
);
</script>

<template>
    <div
        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
    >
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-sm text-muted-foreground">
                {{ eyebrow }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>

                <slot name="after-title" />
            </div>

            <p
                v-if="description"
                class="mt-2 max-w-2xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.actions"
            class="flex flex-col gap-2 sm:flex-row sm:flex-wrap md:justify-end"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
