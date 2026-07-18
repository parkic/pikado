<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    isComplete: boolean;
    hasGroupSetup: boolean;
    groupsSetupUrl: string;
}>();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
        <h2 class="text-lg font-medium">
            Unos učesnika
        </h2>

        <p class="mt-2 text-sm text-muted-foreground">
            <template v-if="isComplete">
                Sva mesta su popunjena. Ako treba da promeniš nekoga,
                koristi opcije Izmeni ili Ukloni u grupama.
            </template>

            <template v-else>
                Prvo podesi grupe da bi unos učesnika bio dostupan.
            </template>
        </p>

        <Link
            v-if="!hasGroupSetup"
            :href="groupsSetupUrl"
            class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Setup grupa
        </Link>
    </div>
</template>
