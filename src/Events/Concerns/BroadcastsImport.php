<?php

namespace Javaabu\Imports\Events\Concerns;

use Illuminate\Broadcasting\PrivateChannel;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Support\Broadcasting;

/**
 * Broadcasts an import event on the import's channel and its owner's channel, when
 * broadcasting is enabled (see Broadcasting::enabled())
 *
 * @property Import $import
 */
trait BroadcastsImport
{
    public function broadcastWhen(): bool
    {
        return Broadcasting::enabled();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel($this->import->broadcastChannel())];

        if ($owner_channel = $this->import->ownerBroadcastChannel()) {
            $channels[] = new PrivateChannel($owner_channel);
        }

        return $channels;
    }

    /**
     * `import.progressed`, `import.completed`, …
     */
    public function broadcastAs(): string
    {
        return 'import.' . strtolower(substr(class_basename(static::class), strlen('Import')));
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return Broadcasting::payload($this->import);
    }
}
