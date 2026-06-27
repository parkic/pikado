<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type VenueResource = {
    id: number;
    name: string;
    type: string;
    sort_order: number;
};

type Venue = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    instagram_url: string | null;
    resources: VenueResource[];
};

defineProps<{
    venue: Venue;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Venue dashboard',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`${venue.name} Dashboard`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <p class="text-sm text-muted-foreground">
                Lokal
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                {{ venue.name }}
            </h1>

            <p
                v-if="venue.description"
                class="mt-2 max-w-2xl text-sm text-muted-foreground"
            >
                {{ venue.description }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Slug</p>
                <p class="mt-2 text-lg font-medium">
                    {{ venue.slug }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Resources</p>
                <p class="mt-2 text-lg font-medium">
                    {{ venue.resources.length }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Status</p>
                <p class="mt-2 text-lg font-medium">
                    Aktivan lokal
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">
                        Resources lokala
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Default oprema koja će se kopirati u konkretan turnir.
                    </p>
                </div>
            </div>

            <div
                v-if="venue.resources.length"
                class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 font-medium">Naziv</th>
                            <th class="px-4 py-3 font-medium">Tip</th>
                            <th class="px-4 py-3 font-medium">Redosled</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="resource in venue.resources"
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
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Ovaj lokal još nema resources.
            </div>
        </div>
    </div>
</template>
