<?php

namespace Javaabu\Imports\Tests\TestSupport\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;

/**
 * Keeps broadcasts in memory so tests can assert on them
 */
class MemoryBroadcaster extends Broadcaster
{
    /** @var array<int, array{channels: array<int, string>, event: string, payload: array<string, mixed>}> */
    public array $broadcasts = [];

    public function auth($request): mixed
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $this->broadcasts[] = [
            'channels' => array_map('strval', $channels),
            'event' => $event,
            'payload' => $payload,
        ];
    }

    /**
     * Run a channel's authorization callback for a user
     */
    public function authorize(string $channel, mixed $user): mixed
    {
        foreach ($this->channels as $pattern => $callback) {
            if (! $this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            $parameters = $this->extractAuthParameters($pattern, $channel, $callback);

            return $callback($user, ...$parameters);
        }

        return null;
    }
}
