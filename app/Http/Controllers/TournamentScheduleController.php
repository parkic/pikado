<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;


class TournamentScheduleController extends Controller
{
    public function index(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $matches = $tournament->matches()
            ->with([
                'group',
                'resource',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
            ])
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TournamentMatch $match) => [
                'id' => $match->id,
                'stage' => $match->stage->value,
                'stage_label' => $this->stageLabel($match->stage),
                'group_name' => $match->group?->name,
                'scheduled_order' => $match->scheduled_order,
                'round_robin_leg' => $match->round_robin_leg,
                'participant_a' => $match->participantA ? [
                    'id' => $match->participantA->id,
                    'group_position' => $match->participantA->group_position,
                    'display_name' => $this->participantDisplayName($match->participantA),
                ] : null,
                'participant_b' => $match->participantB ? [
                    'id' => $match->participantB->id,
                    'group_position' => $match->participantB->group_position,
                    'display_name' => $this->participantDisplayName($match->participantB),
                ] : null,
                'score_a' => $match->score_a,
                'score_b' => $match->score_b,
                'winner' => $match->winner ? [
                    'id' => $match->winner->id,
                    'display_name' => $this->participantDisplayName($match->winner),
                ] : null,
                'status' => $match->status->value,
                'status_label' => $this->statusLabel($match->status),
                'resource' => $match->resource ? [
                    'id' => $match->resource->id,
                    'name' => $match->resource->name,
                    'type' => $match->resource->type->value,
                ] : null,
            ]);

        return Inertia::render('Venues/Tournaments/Schedule/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $tournament->status->value,
                'matches_count' => $matches->count(),
                'group_matches_count' => $matches
                    ->where('stage', MatchStage::GROUP->value)
                    ->count(),
            ],
            'matches' => $matches,
            'resources' => $tournament->resources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TournamentResource $resource) => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => $resource->type->value,
                    'type_label' => $resource->type->value,
                ])
                ->values(),
        ]);
    }

    public function updateResource(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentMatch $match
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($match->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'tournament_resource_id' => [
                'nullable',
                'integer',
                Rule::exists('tournament_resources', 'id')
                    ->where('tournament_id', $tournament->id),
            ],
        ]);

        $match->update([
            'tournament_resource_id' => $validated['tournament_resource_id'] ?? null,
        ]);

        return back()->with('success', 'Resource za meč je promenjen.');
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->player) {
            $name = trim($participant->player->first_name . ' ' . $participant->player->last_name);

            if ($participant->player->nickname) {
                return $name . ' (' . $participant->player->nickname . ')';
            }

            return $name;
        }

        if ($participant->team) {
            return $participant->team->name;
        }

        return 'Nepoznat učesnik';
    }

    private function stageLabel(MatchStage $stage): string
    {
        return match ($stage) {
            MatchStage::GROUP => 'Grupa',
            MatchStage::KNOCKOUT => 'Nokaut',
            MatchStage::THIRD_PLACE => 'Treće mesto',
            MatchStage::FINAL => 'Finale',
        };
    }

    private function statusLabel(MatchStatus $status): string
    {
        return match ($status) {
            MatchStatus::SCHEDULED => 'Scheduled',
            MatchStatus::IN_PROGRESS => 'In Progress',
            MatchStatus::FINISHED => 'Finished',
            MatchStatus::VOIDED => 'Voided',
            MatchStatus::CANCELLED => 'Cancelled',
        };
    }
}
