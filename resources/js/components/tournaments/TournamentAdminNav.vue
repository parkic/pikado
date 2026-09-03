<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    GitFork,
    LayoutDashboard,
    ListOrdered,
    TableProperties,
    Users,
    Waypoints,
} from '@lucide/vue';

import type { TournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentStatus } from '@/types/tournament';

type AdminTournamentPage =
    | 'overview'
    | 'participants'
    | 'schedule'
    | 'standings'
    | 'repechage'
    | 'knockout';

const props = defineProps<{
    active: AdminTournamentPage;
    routes: TournamentRoutes;
    status: TournamentStatus;
    repechageEnabled?: boolean;
}>();

const items = [
    { key: 'overview', label: 'Pregled', icon: LayoutDashboard, route: 'show' },
    { key: 'participants', label: 'Učesnici', icon: Users, route: 'groupDraw' },
    {
        key: 'schedule',
        label: 'Raspored',
        icon: ListOrdered,
        route: 'schedule',
    },
    {
        key: 'standings',
        label: 'Tabela grupa',
        icon: TableProperties,
        route: 'standings',
    },
    { key: 'repechage', label: 'Repasaž', icon: Waypoints, route: 'repechage' },
    { key: 'knockout', label: 'Nokaut', icon: GitFork, route: 'knockout' },
] as const;

const earlyStatuses: TournamentStatus[] = ['draft', 'group_draw', 'ready'];

const isVisible = (key: AdminTournamentPage): boolean => {
    if (key === 'overview' || key === 'participants') {
        return true;
    }

    if (earlyStatuses.includes(props.status)) {
        return false;
    }

    if (key === 'repechage') {
        return Boolean(props.repechageEnabled);
    }

    return true;
};
</script>

<template>
    <nav
        class="overflow-x-auto rounded-2xl border border-border/70 bg-card p-1.5 shadow-sm"
        aria-label="Upravljanje turnirom"
    >
        <div class="flex min-w-max items-center gap-1">
            <template v-for="item in items" :key="item.key">
                <Link
                    v-if="isVisible(item.key)"
                    :href="props.routes[item.route]"
                    class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition"
                    :class="
                        active === item.key
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    :aria-current="active === item.key ? 'page' : undefined"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>
            </template>
        </div>
    </nav>
</template>
