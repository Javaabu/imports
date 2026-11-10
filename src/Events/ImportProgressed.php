<?php

namespace Javaabu\Imports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Events\Concerns\BroadcastsImport;
use Javaabu\Imports\Models\Import;

/**
 * An import saved another chunk of rows
 *
 * Broadcast as `import.progressed` when broadcasting is enabled.
 */
class ImportProgressed implements ShouldBroadcastNow
{
    use BroadcastsImport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Import $import,
    ) {}
}
