<?php

namespace App\Services;

use App\Models\User;

/**
 * Accumule les événements par utilisateur (humains uniquement) et les envoie
 * en un seul lot (GameEventsBatch) lors du flush (toutes les N pools).
 */
class GameEventBuffer
{
    /** @var array<int, array<int, array>> */
    private array $byUser = [];

    public function add(int $userId, array $event): void
    {
        $this->byUser[$userId][] = $event;
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->byUser));
    }

    public function flush(): int
    {
        if (empty($this->byUser)) {
            return 0;
        }

        $sent = 0;
        foreach ($this->byUser as $userId => $events) {
            $user = User::find($userId);
            if ($user) {
                \App\Helpers\BroadcastHelper::safe(
                    fn () => event(new \App\Events\GameEventsBatch($user, $events)),
                    "GameEventsBatch user {$userId}"
                );
                $sent++;
            }
        }

        $this->byUser = [];

        return $sent;
    }
}
