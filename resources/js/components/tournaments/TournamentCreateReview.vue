<script setup lang="ts">
defineProps<{
    publicEnabled: boolean;
    resourcesCount: number;
    processing: boolean;
}>();

const emit = defineEmits<{
    'update:publicEnabled': [value: boolean];
}>();

const updatePublicEnabled = (event: Event) => {
    const input = event.target as HTMLInputElement;

    emit('update:publicEnabled', input.checked);
};
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
        <h2 class="text-lg font-medium">
            Review
        </h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Status
                </span>

                <span class="font-medium">
                    Draft
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Public link
                </span>

                <span class="font-medium">
                    {{ publicEnabled ? 'Uključen' : 'Isključen' }}
                </span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">
                    Resources
                </span>

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
            >

            <span>Public prikaz uključen</span>
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
        >
            {{ processing ? 'Čuvam...' : 'Sačuvaj draft turnir' }}
        </button>
    </div>
</template>
