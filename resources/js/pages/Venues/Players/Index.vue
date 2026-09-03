<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmAction } from '@/composables/useConfirmDialog';

type Player = {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    notes: string | null;
    is_active: boolean;
    tournaments_count: number;
    venues_count: number;
    public_url: string;
    can_delete: boolean;
};

defineProps<{
    players: Player[];
}>();

const deletePlayer = async (player: Player) => {
    const fullName = `${player.first_name} ${player.last_name}`;

    if (
        !(await confirmAction({
            title: 'Obriši igrača?',
            description: `${fullName} će biti uklonjen iz globalnog imenika igrača.`,
            confirmLabel: 'Obriši igrača',
            variant: 'destructive',
        }))
    ) {
        return;
    }

    router.delete(`/admin/players/${player.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Players',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Igrači" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-primary">Aplikacija</p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Igrači
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Globalni imenik igrača, nezavisan od lokala. Jedan profil
                    prati nastupe i statistiku kroz sve lokale i turnire.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/dashboard"
                    class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na dashboard
                </Link>

                <Link
                    href="/admin/players/create"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Dodaj igrača
                </Link>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div
                v-if="players.length"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Ime</th>
                            <th class="px-4 py-3 font-medium">Prezime</th>
                            <th class="px-4 py-3 font-medium">Nadimak</th>
                            <th class="px-4 py-3 font-medium">Nastupi</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Akcije
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="player in players"
                            :key="player.id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3">
                                {{ player.first_name }}
                            </td>

                            <td class="px-4 py-3">
                                {{ player.last_name }}
                            </td>

                            <td class="px-4 py-3 text-muted-foreground">
                                {{ player.nickname ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-muted-foreground">
                                {{ player.tournaments_count }} turnira ·
                                {{ player.venues_count }} lokala
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    v-if="player.is_active"
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

                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a
                                        :href="player.public_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                                    >
                                        Profil
                                    </a>

                                    <Link
                                        :href="`/admin/players/${player.id}/edit`"
                                        class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                                    >
                                        Izmeni
                                    </Link>

                                    <button
                                        v-if="player.can_delete"
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                        @click="deletePlayer(player)"
                                    >
                                        Obriši
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                U aplikaciji još nema igrača.
            </div>
        </div>
    </div>
</template>
