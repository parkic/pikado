<script setup lang="ts">
import type { TournamentResource } from '@/types/tournament';

defineProps<{
    resources: TournamentResource[];
}>();
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">Oprema turnira</h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Table i stolovi dostupni za mečeve ovog turnira.
            </p>
        </div>

        <div
            v-if="resources.length"
            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Naziv</th>

                        <th class="px-4 py-3 font-medium">Tip</th>

                        <th class="px-4 py-3 font-medium">Redosled</th>

                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="resource in resources"
                        :key="resource.id"
                        class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            {{ resource.name }}
                        </td>

                        <td class="px-4 py-3 text-muted-foreground">
                            {{ resource.type_label }}
                        </td>

                        <td class="px-4 py-3 text-muted-foreground">
                            {{ resource.sort_order }}
                        </td>

                        <td class="px-4 py-3 text-muted-foreground">
                            {{ resource.is_active ? 'Aktivan' : 'Neaktivan' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Ovaj turnir nema podešenu opremu.
        </div>
    </div>
</template>
