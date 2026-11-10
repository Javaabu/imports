<?php

namespace Javaabu\Imports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Routing\ImportRoutes;
use Javaabu\Imports\Support\Broadcasting;

/**
 * @mixin Import
 */
class ImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $prefix = ImportRoutes::namePrefix($request);

        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'filename' => $this->filename,
            'format' => $this->format->value,
            'size' => $this->size,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'progress' => $this->progress(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            'skipped_rows' => $this->skipped_rows,
            'failed_rows' => $this->failed_rows,
            'duplicates' => $this->duplicates->value,
            'dry_run' => $this->dry_run,
            'error' => $this->when($this->status === ImportStatuses::FAILED, fn () => $this->error),
            'is_draft' => $this->isDraft(),
            'is_finished' => $this->status->isFinished(),
            'is_cancellable' => $this->isCancellable(),
            'has_report' => $this->hasReport(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'type' => $this->user_type,
                'id' => $this->user_id,
                'name' => $this->user->getAttribute('name'),
            ] : null),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'download' => (bool) $user?->can('download', $this->resource),
                'cancel' => (bool) $user?->can('cancel', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
            'links' => [
                'show' => $this->route("{$prefix}.show"),
                'preview' => $this->isDraft() ? $this->route("{$prefix}.preview") : null,
                'run' => $this->route("{$prefix}.run"),
                'report' => $this->hasReport() ? $this->route("{$prefix}.report") : null,
                'cancel' => $this->route("{$prefix}.cancel"),
                'destroy' => $this->route("{$prefix}.destroy"),
            ],
            'broadcast_channel' => Broadcasting::enabled() ? $this->broadcastChannel() : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function route(string $name): ?string
    {
        return Route::has($name) ? route($name, $this->uuid) : null;
    }
}
