<?php

namespace Javaabu\Imports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Events\Concerns\BroadcastsImport;
use Javaabu\Imports\Models\Import;

/**
 * An import finished; some rows may have failed (see failed_rows)
 *
 * Broadcast as `import.completed` when broadcasting is enabled.
 */
class ImportCompleted implements ShouldBroadcastNow
{
    use BroadcastsImport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Import $import,
    ) {}
}
