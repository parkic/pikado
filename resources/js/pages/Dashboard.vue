<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Building2 } from '@lucide/vue';
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

const roleLabel = (role: string): string => {
    if (role === 'admin') {
        return 'Administrator';
    }

    if (role === 'staff') {
        return 'Osoblje';
    }

    return role;
};

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
            <h1 class="text-2xl font-semibold tracking-tight">Izaberi lokal</h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Nastavi tamo gde organizuješ turnire.
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">
                        Zdravo, {{ userContext.name }}
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Izaberi lokal za upravljanje turnirima.
                    </p>
                </div>
            </div>

            <div
                v-if="userContext.venue_users.length"
                class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="venueUser in userContext.venue_users"
                    :key="venueUser.id"
                    :href="`/venues/${venueUser.venue.slug}`"
                    class="group flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Building2 class="size-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">
                            {{ venueUser.venue.name }}
                        </span>
                        <span
                            class="mt-0.5 block text-sm text-muted-foreground"
                        >
                            {{ roleLabel(venueUser.role) }}
                        </span>
                    </span>

                    <ArrowRight
                        class="size-4 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground"
                    />
                </Link>
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
