<?php

namespace App\Services;

use App\Enums\RepechageOutcomeStatus;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class KnockoutDrawService
{
    public function __construct(
        private readonly GroupStandingsCalculator $standingsCalculator,
    ) {}

    /**
     * Final list of participants eligible for the knockout bracket, ranked
     * across all groups before any manual seeded/unseeded adjustment.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function participants(Tournament $tournament): Collection
    {
        $groups = collect($this->standingsCalculator->calculate($tournament));

        return $groups
            ->flatMap(function (array $group): Collection {
                return collect($group['rows'])
                    ->filter(function (array $row): bool {
                        if ($row['qualification_status'] === 'direct') {
                            return true;
                        }

                        return $row['qualification_status'] === 'repechage'
                            && ($row['repechage_outcome_status'] ?? null) === RepechageOutcomeStatus::ADVANCED->value;
                    })
                    ->map(fn (array $row): array => [
                        'participant_id' => (int) $row['participant_id'],
                        'group_name' => $group['name'],
                        'qualification_position' => $row['qualification_position'],
                        'group_rank' => (int) $row['position'],
                        'display_name' => $row['display_name'],
                        'played' => (int) $row['played'],
                        'wins' => (int) $row['wins'],
                        'losses' => (int) $row['losses'],
                        'points_for' => (int) $row['points_for'],
                        'points_against' => (int) $row['points_against'],
                        'points_difference' => (int) $row['points_difference'],
                        'standing_points' => (int) $row['standing_points'],
                        'source' => $row['qualification_status'] === 'direct' ? 'direct' : 'repechage',
                        'source_label' => $row['qualification_status'] === 'direct'
                            ? 'Direktan prolaz'
                            : 'Prošao iz repasaža',
                        'repechage_outcome_status' => $row['repechage_outcome_status'] ?? null,
                    ]);
            })
            ->sortBy([
                ['group_rank', 'asc'],
                ['standing_points', 'desc'],
                ['wins', 'desc'],
                ['points_difference', 'desc'],
                ['points_for', 'desc'],
                ['display_name', 'asc'],
            ])
            ->values()
            ->map(function (array $participant, int $index): array {
                $participant['seed'] = $index + 1;

                return $participant;
            });
    }

    public function enabled(Tournament $tournament, ?Collection $participants = null): bool
    {
        $participants ??= $this->participants($tournament);

        return (bool) data_get($tournament->settings ?? [], 'repechage_enabled', false)
            && (int) $tournament->knockout_size >= 2
            && $participants->count() === (int) $tournament->knockout_size;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(Tournament $tournament): array
    {
        $participants = $this->participants($tournament);
        $participantIds = $participants->pluck('participant_id')->map(fn ($id) => (int) $id)->all();
        $participantMap = $participants->keyBy('participant_id');
        $seededCount = intdiv($participants->count(), 2);
        $stored = data_get($tournament->settings ?? [], 'knockout_draw', []);
        $storedSeededIds = collect(data_get($stored, 'seeded_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => in_array($id, $participantIds, true))
            ->values();

        $seededIds = $storedSeededIds->count() === $seededCount
            ? $storedSeededIds
            : $participants->take($seededCount)->pluck('participant_id')->map(fn ($id) => (int) $id)->values();
        $unseededIds = collect($participantIds)->diff($seededIds)->values();
        $drawnSeededIds = collect(data_get($stored, 'drawn_seeded_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $seededIds->contains($id))
            ->unique()
            ->values();
        $drawnUnseededIds = collect(data_get($stored, 'drawn_unseeded_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $unseededIds->contains($id))
            ->unique()
            ->values();
        $latestParticipantId = (int) data_get($stored, 'latest_participant_id', 0) ?: null;
        $phase = $drawnSeededIds->count() < $seededIds->count()
            ? 'seeded'
            : ($drawnUnseededIds->count() < $unseededIds->count() ? 'unseeded' : 'complete');

        $summarize = function (int $participantId) use ($participantMap): ?array {
            $participant = $participantMap->get($participantId);

            if (! $participant) {
                return null;
            }

            return [
                'participant_id' => $participantId,
                'display_name' => $participant['display_name'],
                'group_name' => $participant['group_name'],
                'group_rank' => $participant['group_rank'],
                'qualification_position' => $participant['qualification_position'],
                'standing_points' => $participant['standing_points'],
                'wins' => $participant['wins'],
                'points_difference' => $participant['points_difference'],
                'seed' => $participant['seed'],
            ];
        };

        $slotIndexes = $seededCount > 0 ? range(0, $seededCount - 1) : [];
        $slots = collect($slotIndexes)
            ->map(function (int $index) use ($drawnSeededIds, $drawnUnseededIds, $summarize): array {
                $seededId = $drawnSeededIds->get($index);
                $unseededId = $drawnUnseededIds->get($index);

                return [
                    'position' => $index + 1,
                    'seeded' => $seededId ? $summarize((int) $seededId) : null,
                    'unseeded' => $unseededId ? $summarize((int) $unseededId) : null,
                ];
            })
            ->values();

        return [
            'enabled' => $this->enabled($tournament, $participants),
            'started' => $drawnSeededIds->isNotEmpty() || $drawnUnseededIds->isNotEmpty(),
            'complete' => $phase === 'complete' && $participants->isNotEmpty(),
            'phase' => $phase,
            'seeded_count' => $seededCount,
            'can_customize_seeding' => $drawnSeededIds->isEmpty() && $drawnUnseededIds->isEmpty(),
            'seeded' => $seededIds->map($summarize)->filter()->values(),
            'unseeded' => $unseededIds->map($summarize)->filter()->values(),
            'drawn_seeded_ids' => $drawnSeededIds,
            'drawn_unseeded_ids' => $drawnUnseededIds,
            'latest_participant' => $latestParticipantId ? $summarize($latestParticipantId) : null,
            'slots' => $slots,
            'remaining_count' => ($seededIds->count() - $drawnSeededIds->count())
                + ($unseededIds->count() - $drawnUnseededIds->count()),
        ];
    }

    /**
     * @param  array<int, int>  $seededIds
     */
    public function saveSeeding(Tournament $tournament, array $seededIds): void
    {
        $settings = $tournament->settings ?? [];
        data_set($settings, 'knockout_draw', [
            'seeded_ids' => array_values($seededIds),
            'drawn_seeded_ids' => [],
            'drawn_unseeded_ids' => [],
            'latest_participant_id' => null,
        ]);

        $tournament->update(['settings' => $settings]);
    }

    public function reset(Tournament $tournament): void
    {
        $state = $this->state($tournament);
        $this->saveSeeding(
            $tournament,
            collect($state['seeded'])->pluck('participant_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function drawNext(Tournament $tournament): array
    {
        $state = $this->state($tournament);

        if (! $state['enabled'] || $state['complete']) {
            return $state;
        }

        $seededIds = collect($state['seeded'])->pluck('participant_id')->map(fn ($id) => (int) $id)->values();
        $unseededIds = collect($state['unseeded'])->pluck('participant_id')->map(fn ($id) => (int) $id)->values();
        $drawnSeededIds = collect($state['drawn_seeded_ids'])->map(fn ($id) => (int) $id)->values();
        $drawnUnseededIds = collect($state['drawn_unseeded_ids'])->map(fn ($id) => (int) $id)->values();

        if ($state['phase'] === 'seeded') {
            $remaining = $seededIds->diff($drawnSeededIds)->values();
            $pickedId = (int) $remaining[random_int(0, $remaining->count() - 1)];
            $drawnSeededIds->push($pickedId);
        } else {
            $remaining = $unseededIds->diff($drawnUnseededIds)->values();
            $slotIndex = $drawnUnseededIds->count();
            $seededParticipant = collect($state['seeded'])
                ->firstWhere('participant_id', $drawnSeededIds->get($slotIndex));
            $differentGroup = $remaining
                ->filter(function (int $participantId) use ($state, $seededParticipant): bool {
                    $candidate = collect($state['unseeded'])->firstWhere('participant_id', $participantId);

                    return ! $seededParticipant
                        || ! $candidate
                        || $candidate['group_name'] !== $seededParticipant['group_name'];
                })
                ->values();
            $pool = $differentGroup->isNotEmpty() ? $differentGroup : $remaining;
            $pickedId = (int) $pool[random_int(0, $pool->count() - 1)];
            $drawnUnseededIds->push($pickedId);
        }

        $settings = $tournament->settings ?? [];
        data_set($settings, 'knockout_draw', [
            'seeded_ids' => $seededIds->all(),
            'drawn_seeded_ids' => $drawnSeededIds->all(),
            'drawn_unseeded_ids' => $drawnUnseededIds->all(),
            'latest_participant_id' => $pickedId,
        ]);
        $tournament->update(['settings' => $settings]);

        return $this->state($tournament->fresh());
    }
}
