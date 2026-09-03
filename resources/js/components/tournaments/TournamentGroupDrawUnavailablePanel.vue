<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    isComplete: boolean;
    hasGroupSetup: boolean;
    canManageRoster: boolean;
    groupsSetupUrl: string;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 xl:sticky xl:top-4 xl:self-start dark:border-sidebar-border"
    >
        <h2 class="text-lg font-medium">
            {{
                !hasGroupSetup
                    ? 'Grupe nisu podešene'
                    : !canManageRoster
                      ? 'Unos učesnika je zatvoren'
                      : 'Unos učesnika je završen'
            }}
        </h2>

        <p class="mt-2 text-sm text-muted-foreground">
            <template v-if="!hasGroupSetup">
                Prvo podesi grupe da bi unos učesnika bio dostupan.
            </template>

            <template v-else-if="!canManageRoster">
                Grupna faza je završena. Promene sastava više nisu dostupne.
            </template>

            <template v-else-if="isComplete">
                Sva mesta su popunjena. Akcije Izmeni, Zameni, Odustao i Ukloni
                dostupne su u meniju sa tri tačke uz učesnika.
            </template>
        </p>

        <Link
            v-if="!hasGroupSetup"
            :href="groupsSetupUrl"
            class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Podesi grupe
        </Link>
    </div>
</template>
