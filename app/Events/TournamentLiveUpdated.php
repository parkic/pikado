<?php

namespace App\Events;

use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentLiveUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public string $reason = 'updated',
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('public-tournament.' . $this->tournament->public_code);
    }

    public function broadcastAs(): string
    {
        return 'TournamentLiveUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'public_code' => $this->tournament->public_code,
            'reason' => $this->reason,
        ];
    }
}
