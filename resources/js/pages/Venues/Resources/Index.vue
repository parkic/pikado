<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type Resource = {
    id: number;
    name: string;
    type: string;
    sort_order: number;
    is_active: boolean;
};

defineProps<{
    venue: Venue;
    resources: Resource[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Venue resources',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`${venue.name} Resources`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Resources lokala
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Default oprema lokala. Kasnije će se ovi resources kopirati u konkretan turnir.
                </p>
            </div>

            <Link
                :href="`/venues/${venue.slug}/dashboard`"
                class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na dashboard
            </Link>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div
                v-if="resources.length"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
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
                                {{ resource.type }}
                            </td>

                            <td class="px-4 py-3 text-muted-foreground">
                                {{ resource.sort_order }}
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    v-if="resource.is_active"
                                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium"
                                >
                                    Aktivan
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    Neaktivan
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Ovaj lokal još nema resources.
            </div>
        </div>
    </div>
</template>
