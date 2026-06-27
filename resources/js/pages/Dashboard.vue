<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

type VenueUser = {
    id: number;
    role: string;
    venue: {
        id: number;
        name: string;
        slug: string;
    };
};

type UserContext = {
    id: number;
    name: string;
    email: string;
    global_role: string | null;
    venue_users: VenueUser[];
};

defineProps<{
    userContext: UserContext;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Pikado Dashboard
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Osnovni admin kontekst za ulogovanog korisnika.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Korisnik</p>
                <p class="mt-2 text-lg font-medium">
                    {{ userContext.name }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ userContext.email }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Globalna rola</p>
                <p class="mt-2 text-lg font-medium">
                    {{ userContext.global_role ?? 'Nema globalnu rolu' }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Lokali</p>
                <p class="mt-2 text-lg font-medium">
                    {{ userContext.venue_users.length }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">
                        Moji lokali
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Lokali kojima ovaj korisnik ima pristup.
                    </p>
                </div>
            </div>

            <div
                v-if="userContext.venue_users.length"
                class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 font-medium">Lokal</th>
                            <th class="px-4 py-3 font-medium">Slug</th>
                            <th class="px-4 py-3 font-medium">Rola</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="venueUser in userContext.venue_users"
                            :key="venueUser.id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3">
                                {{ venueUser.venue.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ venueUser.venue.slug }}
                            </td>
                            <td class="px-4 py-3">
                                {{ venueUser.role }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Ovaj korisnik trenutno nema pristup nijednom lokalu.
            </div>
        </div>
    </div>
</template>
