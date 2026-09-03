<script setup lang="ts">
import { EllipsisVertical, RotateCcw, UserX } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type {
    TournamentStandingGroup,
    TournamentStandingRow,
    TournamentStatus,
} from '@/types/tournament';

defineProps<{
    group: TournamentStandingGroup;
    canManageWithdrawals: boolean;
    tournamentStatus: TournamentStatus;
}>();

const emit = defineEmits<{
    'update-qualification-override': [
        row: TournamentStandingRow,
        value: string,
    ];
    'withdraw-participant': [row: TournamentStandingRow, policy: string];
    'restore-participant': [row: TournamentStandingRow];
}>();

const differenceLabel = (difference: number): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const rowStatusClasses = (row: TournamentStandingRow): string => {
    if (row.is_withdrawn || row.qualification_status === 'withdrawn') {
        return 'bg-fuchsia-500/[0.16] shadow-[inset_4px_0_0_0_rgba(217,70,239,0.9)] ring-1 ring-inset ring-fuchsia-500/25 hover:bg-fuchsia-500/[0.22]';
    }

    if (row.qualification_status === 'direct') {
        return 'bg-emerald-500/[0.075] hover:bg-emerald-500/[0.11]';
    }

    if (row.qualification_status === 'repechage') {
        return 'bg-amber-400/[0.14] hover:bg-amber-400/[0.19]';
    }

    if (row.qualification_status === 'eliminated') {
        return 'bg-red-500/[0.11] hover:bg-red-500/[0.16]';
    }

    return 'bg-zinc-500/[0.045] hover:bg-zinc-500/[0.075]';
};

const updateQualificationOverride = (
    row: TournamentStandingRow,
    value: unknown,
) => {
    if (typeof value !== 'string') {
        return;
    }

    emit(
        'update-qualification-override',
        row,
        value === 'automatic' ? '' : value,
    );
};

const qualificationMenuValue = (row: TournamentStandingRow): string =>
    row.qualification_override_status ?? 'automatic';

const withdrawalPolicyLabel = (policy: string | null): string => {
    if (policy === 'keep_played_average_rest') {
        return 'Odigrano važi · ostalo obračunato po proseku';
    }

    if (policy === 'keep_played_manual_rest') {
        return 'Odigrano važi · ostalo čeka ručni unos';
    }

    return 'Svi mečevi anulirani';
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <div>
                <h2 class="text-lg font-medium">Grupa {{ group.name }}</h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Odigrano
                    {{ group.finished_matches_count }}
                    /
                    {{ group.matches_count }}
                    mečeva.
                </p>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span
                        class="inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-700 dark:text-emerald-300"
                    >
                        Direktan prolaz
                    </span>

                    <span
                        class="inline-flex rounded-full bg-yellow-500/10 px-2.5 py-1 font-medium text-yellow-700 dark:text-yellow-300"
                    >
                        Repasaž
                    </span>

                    <span
                        class="inline-flex rounded-full bg-red-500/10 px-2.5 py-1 font-medium text-red-700 dark:text-red-300"
                    >
                        Ispao
                    </span>

                    <span
                        class="inline-flex rounded-full bg-fuchsia-500/15 px-2.5 py-1 font-semibold text-fuchsia-700 ring-1 ring-fuchsia-500/25 ring-inset dark:text-fuchsia-300"
                    >
                        Odustao
                    </span>
                </div>
            </div>
        </div>

        <div
            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-3 py-3 font-medium">#</th>

                            <th class="px-3 py-3 font-medium">Učesnik</th>

                            <th class="px-3 py-3 text-center font-medium">O</th>

                            <th class="px-3 py-3 text-center font-medium">P</th>

                            <th class="px-3 py-3 text-center font-medium">I</th>

                            <th class="px-3 py-3 text-center font-medium">
                                Za
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Protiv
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                +/-
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Bod
                            </th>

                            <th
                                class="sticky right-0 z-20 w-14 border-l border-sidebar-border/70 bg-muted px-3 py-3 text-right font-medium dark:border-sidebar-border"
                            >
                                <span class="sr-only">Akcije</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in group.rows"
                            :key="row.participant_id"
                            class="group border-b border-sidebar-border/70 transition-colors last:border-b-0 dark:border-sidebar-border"
                            :class="rowStatusClasses(row)"
                            :data-qualification-status="
                                row.qualification_status
                            "
                        >
                            <td class="px-3 py-3 text-muted-foreground">
                                {{ row.position ?? '-' }}
                            </td>

                            <td class="px-3 py-3">
                                <div
                                    class="font-medium"
                                    :class="
                                        row.is_withdrawn
                                            ? 'font-semibold text-fuchsia-700 line-through decoration-2 dark:text-fuchsia-300'
                                            : ''
                                    "
                                >
                                    {{ row.display_name }}
                                </div>

                                <div
                                    v-if="row.is_withdrawn && row.withdrawn_at"
                                    class="mt-1 text-xs font-semibold text-fuchsia-700 dark:text-fuchsia-300"
                                >
                                    Odustao:
                                    {{ row.withdrawn_at }}
                                </div>
                                <div
                                    v-if="row.is_withdrawn"
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    {{
                                        withdrawalPolicyLabel(
                                            row.withdrawal_policy,
                                        )
                                    }}
                                </div>
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.played }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.wins }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.losses }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.points_for }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.points_against }}
                            </td>

                            <td class="px-3 py-3 text-center font-medium">
                                {{ differenceLabel(row.points_difference) }}
                            </td>

                            <td class="px-3 py-3 text-center font-semibold">
                                {{ row.standing_points }}
                            </td>

                            <td
                                class="sticky right-0 z-10 w-14 border-l border-sidebar-border/70 bg-inherit px-3 py-3 text-right dark:border-sidebar-border"
                            >
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-foreground/70 transition hover:bg-muted hover:text-foreground"
                                            :aria-label="`Akcije za ${row.display_name}`"
                                            title="Akcije učesnika"
                                        >
                                            <EllipsisVertical class="size-4" />
                                        </button>
                                    </DropdownMenuTrigger>

                                    <DropdownMenuContent
                                        align="end"
                                        class="w-60"
                                    >
                                        <DropdownMenuLabel>
                                            <span
                                                class="block text-xs font-normal text-muted-foreground"
                                            >
                                                Trenutni status
                                            </span>
                                            <span
                                                class="mt-0.5 block font-medium"
                                            >
                                                {{ row.qualification_label }}
                                            </span>
                                            <span
                                                v-if="
                                                    row.qualification_is_manual
                                                "
                                                class="mt-0.5 block text-[11px] font-normal text-primary"
                                            >
                                                Ručno podešeno
                                            </span>
                                        </DropdownMenuLabel>

                                        <template v-if="!row.is_withdrawn">
                                            <DropdownMenuSeparator />
                                            <DropdownMenuLabel
                                                class="text-xs font-normal text-muted-foreground"
                                            >
                                                Podesi prolaz
                                            </DropdownMenuLabel>
                                            <DropdownMenuRadioGroup
                                                :model-value="
                                                    qualificationMenuValue(row)
                                                "
                                                @update:model-value="
                                                    updateQualificationOverride(
                                                        row,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <DropdownMenuRadioItem
                                                    value="automatic"
                                                >
                                                    Automatski
                                                </DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem
                                                    value="direct"
                                                >
                                                    Direktan prolaz
                                                </DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem
                                                    value="repechage"
                                                >
                                                    Repasaž
                                                </DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem
                                                    value="eliminated"
                                                >
                                                    Ispao
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </template>

                                        <template
                                            v-if="
                                                canManageWithdrawals &&
                                                tournamentStatus ===
                                                    'group_stage'
                                            "
                                        >
                                            <DropdownMenuSeparator />
                                            <template v-if="!row.is_withdrawn">
                                                <DropdownMenuLabel
                                                    class="text-xs font-normal text-muted-foreground"
                                                >
                                                    Odustajanje — izaberi
                                                    obračun
                                                </DropdownMenuLabel>
                                                <DropdownMenuItem
                                                    class="cursor-pointer text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                    @select="
                                                        emit(
                                                            'withdraw-participant',
                                                            row,
                                                            'void_all',
                                                        )
                                                    "
                                                >
                                                    <UserX class="size-3.5" />
                                                    Anuliraj sve mečeve
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="cursor-pointer"
                                                    @select="
                                                        emit(
                                                            'withdraw-participant',
                                                            row,
                                                            'keep_played_average_rest',
                                                        )
                                                    "
                                                >
                                                    <UserX class="size-3.5" />
                                                    Zadrži odigrano · prosek za
                                                    ostalo
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="cursor-pointer"
                                                    @select="
                                                        emit(
                                                            'withdraw-participant',
                                                            row,
                                                            'keep_played_manual_rest',
                                                        )
                                                    "
                                                >
                                                    <UserX class="size-3.5" />
                                                    Zadrži odigrano · ručni unos
                                                </DropdownMenuItem>
                                            </template>

                                            <DropdownMenuItem
                                                v-else
                                                class="cursor-pointer"
                                                @select="
                                                    emit(
                                                        'restore-participant',
                                                        row,
                                                    )
                                                "
                                            >
                                                <RotateCcw class="size-3.5" />
                                                Vrati u turnir
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            Sortiranje: pobeda nosi 1 bod, zatim razlika, poeni za, ime.
        </p>
    </div>
</template>
