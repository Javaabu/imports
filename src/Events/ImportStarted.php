<?php

namespace Javaabu\Imports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Events\Concerns\BroadcastsImport;
use Javaabu\Imports\Models\Import;

/**
 * An import started processing
 *
 * Broadcast as `import.started` when broadcasting is enabled.
 */
class ImportStarted implements ShouldBroadcastNow
{
    use BroadcastsImport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Import $import,
    ) {}
}
