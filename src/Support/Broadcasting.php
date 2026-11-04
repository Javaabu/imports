<?php

namespace Javaabu\Imports\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Models\Import;

/**
 * Whether import progress is broadcast (over Laravel Reverb), the channels it uses,
 * and how often pages poll when it isn't
 */
class Broadcasting
{
    /** The class whose presence means laravel/reverb is installed */
    public static string $reverb_class = 'Laravel\\Reverb\\ReverbServiceProvider';

    /**
     * `imports.broadcasting.enabled`: true, false, or 'auto' (when laravel/reverb is
     * installed and the default broadcaster isn't null or log)
     */
    public static function enabled(): bool
    {
        $enabled = config('imports.broadcasting.enabled', 'auto');

        if ($enabled !== 'auto') {
            return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        if (! class_exists(static::$reverb_class)) {
            return false;
        }

        return ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }

    /**
     * Milliseconds between progress checks for pages that poll
     */
    public static function pollInterval(): int
    {
        return max((int) config('imports.broadcasting.poll_interval', 3000), 500);
    }

    public static function channelPrefix(): string
    {
        return (string) config('imports.broadcasting.channel', 'imports');
    }

    /**
     * Authorize the private channels: `imports.{uuid}` for whoever may view the import,
     * `imports.owner.{type}.{id}` for the owner
     */
    public static function registerChannels(): void
    {
        $prefix = static::channelPrefix();
        $options = array_filter(['guards' => config('imports.broadcasting.guards')]);

        Broadcast::channel("{$prefix}.owner.{type}.{id}", function (Model $user, string $type, string $id) {
            return static::encodeType($user->getMorphClass()) === $type
                && (string) $user->getKey() === $id;
        }, $options);

        Broadcast::channel("{$prefix}.{uuid}", function (Model $user, string $uuid) {
            $import = Imports::importModel()::query()->where('uuid', $uuid)->first();

            return $import !== null && $user->can('view', $import);
        }, $options);
    }

    /**
     * A morph type that's safe in a channel name: `App\Models\User` → `App-Models-User`
     */
    public static function encodeType(string $type): string
    {
        return str_replace(['\\', '.'], ['-', '='], $type);
    }

    /**
     * What progress events send: enough for a page to update a row
     *
     * @return array<string, mixed>
     */
    public static function payload(Import $import): array
    {
        return [
            'id' => $import->uuid,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'status_color' => $import->status->color(),
            'progress' => $import->progress(),
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'created_rows' => $import->created_rows,
            'updated_rows' => $import->updated_rows,
            'skipped_rows' => $import->skipped_rows,
            'failed_rows' => $import->failed_rows,
            'is_finished' => $import->status->isFinished(),
            'has_report' => $import->hasReport(),
            'error' => $import->error,
        ];
    }
}
