<?php

namespace Javaabu\Imports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Events\Concerns\BroadcastsImport;
use Javaabu\Imports\Models\Import;

/**
 * An import stopped with an error
 *
 * Broadcast as `import.failed` when broadcasting is enabled.
 */
class ImportFailed implements ShouldBroadcastNow
{
    use BroadcastsImport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Import $import,
    ) {}
}
