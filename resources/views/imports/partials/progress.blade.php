@php($progress = $import->progress())
<div class="flex min-w-32 flex-col gap-1" data-import-progress>
    <div class="flex items-center gap-2">
        <div class="h-2 w-full overflow-hidden rounded-full bg-muted"
             role="progressbar"
             aria-label="{{ __('Progress') }}"
             aria-valuemin="0"
             aria-valuemax="100"
             @if($progress !== null) aria-valuenow="{{ $progress }}" @endif>
            <div class="h-full rounded-full bg-primary transition-[inline-size] {{ $progress === null && $import->status->isCancellable() ? 'animate-pulse' : '' }}"
                 style="inline-size: {{ $progress ?? ($import->status->isCancellable() ? 33 : 0) }}%"
                 data-import-progress-bar></div>
        </div>
        <span class="text-sm tabular-nums text-muted-foreground" data-import-progress-text>{{ $progress === null ? '' : "{$progress}%" }}</span>
    </div>
    <span class="text-xs tabular-nums text-muted-foreground" data-import-rows>{{ $import->total_rows === null ? '' : __(':processed of :total rows', ['processed' => number_format($import->processed_rows), 'total' => number_format($import->total_rows)]) }}</span>
</div>
